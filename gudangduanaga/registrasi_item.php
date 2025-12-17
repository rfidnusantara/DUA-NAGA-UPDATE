<?php
// registrasi_manage.php (FULL - gabungan, 1 file)
// - AJAX preview JSON via ?ajax=1 (tanpa redirect HTML)
// - Live preview: registered/unregistered + info item
// - Submit: tag sudah ada di DB di-skip, tag baru disimpan (bukan gagal total)
// - Panel kanan: "Hasil Pembacaan (Live)" (menggantikan Riwayat Registrasi)

require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ==========================
 * AJAX JSON HANDLER (preview)
 * ==========================
 * Harus di atas auth.php supaya tidak redirect login (HTML) saat fetch().
 */
$isJsonRequest = (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
$isAjaxPreview = (
    isset($_GET['ajax']) && $_GET['ajax'] === '1' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $isJsonRequest
);

if ($isAjaxPreview) {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized (session login tidak terbaca). Silakan refresh & login ulang.'
        ]);
        exit;
    }

    try {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) $payload = [];

        $tagsRaw = $payload['tags'] ?? [];
        if (!is_array($tagsRaw)) $tagsRaw = [];

        $tags = [];
        foreach ($tagsRaw as $t) {
            $t = trim((string)$t);
            if ($t !== '') $tags[] = $t;
        }
        $tags = array_values(array_unique($tags));
        if (count($tags) > 500) $tags = array_slice($tags, 0, 500);

        if (empty($tags)) {
            echo json_encode([
                'success' => true,
                'items'   => [],
                'counts'  => ['total' => 0, 'registered' => 0, 'unregistered' => 0],
            ]);
            exit;
        }

        // Ambil data registrasi terbaru per tag
        $foundMap = [];
        $chunkSize = 150;

        for ($i = 0; $i < count($tags); $i += $chunkSize) {
            $chunk = array_slice($tags, $i, $chunkSize);
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $sql = "
                SELECT id, rfid_tag, product_name, po_number, so_number, name_label, batch_number, pcs, created_at
                FROM rfid_registrations
                WHERE rfid_tag IN ($placeholders)
                ORDER BY rfid_tag ASC, created_at DESC, id DESC
            ";
            $st = $pdo->prepare($sql);
            $st->execute($chunk);

            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $tag = (string)($row['rfid_tag'] ?? '');
                if ($tag === '') continue;
                if (!isset($foundMap[$tag])) $foundMap[$tag] = $row; // newest per tag
            }
        }

        $items = [];
        $regCount = 0;

        foreach ($tags as $t) {
            $row = $foundMap[$t] ?? null;
            if ($row) $regCount++;

            $items[] = [
                'tag'          => $t,
                'registered'   => $row ? true : false,
                'product_name' => $row['product_name'] ?? '',
                'po_number'    => $row['po_number'] ?? '',
                'so_number'    => $row['so_number'] ?? '',
                'name_label'   => $row['name_label'] ?? '',
                'batch_number' => $row['batch_number'] ?? '',
                'pcs'          => isset($row['pcs']) ? (int)$row['pcs'] : 0,
                'created_at'   => $row['created_at'] ?? '',
            ];
        }

        echo json_encode([
            'success' => true,
            'items'   => $items,
            'counts'  => [
                'total'        => count($tags),
                'registered'   => $regCount,
                'unregistered' => count($tags) - $regCount,
            ]
        ]);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Preview error: ' . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * ==========================
 * PAGE MODE (normal HTML)
 * ==========================
 */
require_once 'auth.php'; // auth.php sudah require functions.php + refresh session

$pageTitle = 'Registrasi Manage';

// ==========================
// Helper Normalisasi Nama PT/CV
// ==========================
function normalize_company_name(string $name): string {
    $name = trim($name);
    if ($name === '') return '';
    $name = preg_replace('/^(PT|CV)\.?\s+/i', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

// ==========================
// 0) Ambil user login + hak akses
// ==========================
$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    header('Location: login.php');
    exit;
}
$userRole = $currentUser['role'] ?? 'user';

// ==========================
// 0A) Tentukan perusahaan user (AUTO FILTER)
// ==========================
$userCompanyRaw = '';

// via warehouse_id
if (!empty($currentUser['warehouse_id'])) {
    try {
        $stmtW = $pdo->prepare("SELECT name FROM warehouses WHERE id = :id LIMIT 1");
        $stmtW->execute([':id' => (int)$currentUser['warehouse_id']]);
        $userCompanyRaw = (string)($stmtW->fetchColumn() ?: '');
    } catch (Exception $e) {}
}
// fallback
if ($userCompanyRaw === '') {
    foreach (['company_name','warehouse_name','company','warehouse','name'] as $k) {
        if (!empty($currentUser[$k])) { $userCompanyRaw = (string)$currentUser[$k]; break; }
    }
}

$userCompanyNorm = normalize_company_name($userCompanyRaw);

// Alias
$aliases = [
    'Phytomed Neo Farma'    => 'Phytomed Neo Farmapo',
    'Phytomed Neo Farmapo'  => 'Phytomed Neo Farmapo',
];
if (isset($aliases[$userCompanyNorm])) $userCompanyNorm = $aliases[$userCompanyNorm];

// ==========================
// 1) Filter perusahaan
// ==========================
$selectedCompany = normalize_company_name(trim($_GET['company'] ?? ''));

// Kunci non-admin
$forceCompany = false;
if ($userRole !== 'admin') {
    if ($userCompanyNorm !== '') {
        $selectedCompany = $userCompanyNorm;
        $forceCompany = true;
    }
}

// ==========================
// 2) Konfigurasi Reader
// ==========================
$RFID_READERS = [
    'zweena_reg'        => ['label' => 'Produksi - CV. Zweena Adi Nugraha (Registrasi)'],
    'dnk_gambiran_reg'  => ['label' => 'PT. Dua Naga Kosmetindo - Gambiran (Registrasi)'],
    'dnk_teblon_reg'    => ['label' => 'PT. Dua Naga Kosmetindo - Teblon (Registrasi)'],
    'central_inout'     => ['label' => 'Gudang Central (Barang Masuk/Keluar)'],
];

$companyReaderMap = [
    'Zweena Adi Nugraha'   => ['zweena_reg'],
    'Dua Naga Kosmetindo'  => ['dnk_gambiran_reg', 'dnk_teblon_reg'],
    'Phytomed Neo Farmapo' => [],
    'Indo Naga Food'       => [],
];

$visibleReaderKeys = array_keys($RFID_READERS);
if ($userRole !== 'admin' && $selectedCompany !== '') {
    $mapped = $companyReaderMap[$selectedCompany] ?? null;
    if (is_array($mapped)) {
        $mapped = array_values(array_filter($mapped, fn($k) => isset($RFID_READERS[$k])));
        if (!empty($mapped)) $visibleReaderKeys = $mapped;
    }
}

$visibleReaders = [];
foreach ($visibleReaderKeys as $k) $visibleReaders[$k] = $RFID_READERS[$k];

$selectedReader = trim($_POST['reader'] ?? $_GET['reader'] ?? ($visibleReaderKeys[0] ?? 'zweena_reg'));
if (!isset($RFID_READERS[$selectedReader])) $selectedReader = $visibleReaderKeys[0] ?? 'zweena_reg';
if ($userRole !== 'admin' && !isset($visibleReaders[$selectedReader])) $selectedReader = $visibleReaderKeys[0] ?? $selectedReader;

// ==========================
// 3) Ambil data API
// ==========================
$salesOrdersAll = fetch_sales_orders_from_api(1, 50);
if (is_array($salesOrdersAll) && isset($salesOrdersAll['data']) && is_array($salesOrdersAll['data'])) {
    $salesOrdersAll = $salesOrdersAll['data'];
}
if (!is_array($salesOrdersAll)) $salesOrdersAll = [];

$companyOptions = [];
foreach ($salesOrdersAll as $order) {
    $companyNameRaw  = $order['company']['name'] ?? '';
    $companyNameNorm = normalize_company_name((string)$companyNameRaw);
    if (isset($aliases[$companyNameNorm])) $companyNameNorm = $aliases[$companyNameNorm];
    if ($companyNameNorm !== '') $companyOptions[$companyNameNorm] = $companyNameNorm;
}

if ($selectedCompany !== '') {
    $salesOrders = array_filter($salesOrdersAll, function ($order) use ($selectedCompany, $aliases) {
        $companyNameRaw  = (string)($order['company']['name'] ?? '');
        $companyNameNorm = normalize_company_name($companyNameRaw);
        if (isset($aliases[$companyNameNorm])) $companyNameNorm = $aliases[$companyNameNorm];
        return $companyNameNorm === $selectedCompany;
    });
} else {
    $salesOrders = $salesOrdersAll;
}

// ==========================
// 4) Handle Submit (SAVE)
// ==========================
$error = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiProductId = (int)($_POST['api_product_id'] ?? 0);
    $productName  = trim($_POST['product_name'] ?? '');
    $poNumber     = trim($_POST['po_number'] ?? '');
    $soNumber     = trim($_POST['so_number'] ?? '');
    $nameLabel    = trim($_POST['name_label'] ?? '');
    $batchNumber  = trim($_POST['batch_number'] ?? '');
    $pcs          = (int)($_POST['pcs'] ?? 0);
    $rfidRaw      = trim($_POST['rfid_tag'] ?? '');

    $selectedReader = trim($_POST['reader'] ?? $selectedReader);
    if (!isset($RFID_READERS[$selectedReader])) $selectedReader = $visibleReaderKeys[0] ?? 'zweena_reg';
    if ($userRole !== 'admin' && !isset($visibleReaders[$selectedReader])) $selectedReader = $visibleReaderKeys[0] ?? $selectedReader;

    if ($apiProductId <= 0 || $productName === '') {
        $error = 'Silakan pilih data dari API (PO/SO/Product) terlebih dahulu.';
    } elseif ($pcs <= 0) {
        $error = 'Pcs harus lebih dari 0.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag tidak boleh kosong (klik Start lalu scan).';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_values(array_filter($tags, fn($t) => $t !== ''));

        if (empty($tags)) {
            $error = 'RFID Tag tidak valid. Pastikan ada minimal 1 tag.';
        } else {
            // 1) Cek double dalam form
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) if ($cnt > 1) $dupInForm[] = $tag;

            if (!empty($dupInForm)) {
                $error = 'Terdapat RFID Tag double di form (tidak disimpan): ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags));

                // 2) Cek yang sudah ada di DB (skip)
                $cek = $pdo->prepare("SELECT COUNT(*) FROM rfid_registrations WHERE rfid_tag = :tag");
                $existingTags = [];
                $newTags = [];

                foreach ($tags as $tag) {
                    $cek->execute([':tag' => $tag]);
                    $jumlah = (int)$cek->fetchColumn();
                    if ($jumlah > 0) $existingTags[] = $tag;
                    else $newTags[] = $tag;
                }

                if (empty($newTags)) {
                    $error = 'Tidak ada RFID Tag baru untuk disimpan. Semua tag sudah terdaftar.';
                } else {
                    // 3) Simpan hanya tag baru
                    $stmt = $pdo->prepare("
                        INSERT INTO rfid_registrations
                            (api_product_id, product_name, po_number, so_number, name_label, batch_number, pcs, rfid_tag)
                        VALUES
                            (:api_product_id, :product_name, :po, :so, :name_label, :batch, :pcs, :rfid)
                    ");

                    foreach ($newTags as $rfidTag) {
                        $stmt->execute([
                            ':api_product_id' => $apiProductId,
                            ':product_name'   => $productName,
                            ':po'             => $poNumber,
                            ':so'             => $soNumber,
                            ':name_label'     => $nameLabel,
                            ':batch'          => $batchNumber,
                            ':pcs'            => $pcs,
                            ':rfid'           => $rfidTag,
                        ]);
                    }

                    $successMsg = 'Sukses! Tag baru tersimpan: ' . count($newTags) . ' item.';
                    if (!empty($existingTags)) {
                        $successMsg .= ' (Skip sudah terdaftar: ' . implode(', ', $existingTags) . ')';
                    }

                    // refresh page agar form tetap enak
                    $redirCompany = $selectedCompany;
                    header('Location: registrasi_manage.php?success=1&msg=' . urlencode($successMsg) . '&company=' . urlencode($redirCompany) . '&reader=' . urlencode($selectedReader));
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['success']) && isset($_GET['msg']) && $successMsg === '') {
    $successMsg = (string)$_GET['msg'];
}

// ==========================
// Render
// ==========================
include 'layout/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .rfid-console {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-family: 'Consolas','Monaco','Courier New', monospace;
        font-size: 0.9rem;
        border: 1px solid #ced4da;
        letter-spacing: .4px;
    }
    .rfid-console:focus {
        background-color: #fff;
        border-color: #0d6efd;
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.15);
    }
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(25,135,84,.7); }
        70% { box-shadow: 0 0 0 6px rgba(25,135,84,0); }
        100% { box-shadow: 0 0 0 0 rgba(25,135,84,0); }
    }
    .status-scanning { background-color: #198754 !important; animation: pulse-green 1.5s infinite; }
    .status-idle { background-color: #6c757d !important; }

    .scan-controls {
        background: #eef2f6;
        border-radius: 10px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
    }

    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--bootstrap-5 .select2-selection { border-color: #ced4da; }

    #previewContainer{
        min-height: 140px;
        max-height: 520px;
        overflow: auto;
    }
    .mono{ font-family: 'Consolas','Monaco','Courier New', monospace; }
</style>

<div class="row g-4">
    <!-- KIRI: FORM -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-pencil-square me-2"></i>Form Registrasi Item
                </h6>
            </div>
            <div class="card-body">

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (empty($salesOrdersAll)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-wifi-off me-2"></i> Gagal terhubung ke API / Data kosong.
                    </div>
                <?php endif; ?>

                <?php if ($userRole !== 'admin' && $forceCompany && ($companyReaderMap[$selectedCompany] ?? null) === []): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Perusahaan Anda terkunci ke <b><?= htmlspecialchars($selectedCompany); ?></b>,
                        namun reader khusus belum dipetakan (cek <code>$companyReaderMap</code>).
                    </div>
                <?php endif; ?>

                <!-- Filter perusahaan -->
                <form method="get" class="mb-3">
                    <input type="hidden" name="reader" value="<?= htmlspecialchars($selectedReader); ?>">
                    <label class="form-label small text-muted fw-bold text-uppercase">Filter Perusahaan</label>

                    <?php if ($userRole !== 'admin' && $forceCompany): ?>
                        <select class="form-select" disabled>
                            <option selected><?= htmlspecialchars($selectedCompany); ?></option>
                        </select>
                        <input type="hidden" name="company" value="<?= htmlspecialchars($selectedCompany); ?>">
                        <div class="form-text small">Perusahaan terkunci sesuai hak user.</div>
                    <?php else: ?>
                        <select name="company" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Semua Perusahaan --</option>
                            <?php ksort($companyOptions); foreach ($companyOptions as $companyName): ?>
                                <option value="<?= htmlspecialchars($companyName); ?>"
                                    <?= ($selectedCompany === $companyName) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($companyName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Pilih perusahaan untuk membatasi PO / SO di bawah.</div>
                    <?php endif; ?>
                </form>

                <!-- FORM UTAMA -->
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Nomor PO</label>
                        <select id="apiSelect" class="form-select" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                            <option value=""></option>
                            <?php foreach ($salesOrders as $order):
                                $po   = $order['customer_order']['number'] ?? '';
                                $so   = $order['number'] ?? '';
                                $prod = $order['item']['product']['name'] ?? '';
                                $pid  = $order['item']['product']['id'] ?? 0;
                                $qty  = $order['item']['quantity'] ?? 1;

                                $custName = $order['customer']['name'] ?? '';

                                $batchNumbers = [];
                                if (!empty($order['batches']) && is_array($order['batches'])) {
                                    foreach ($order['batches'] as $b) if (!empty($b['number'])) $batchNumbers[] = $b['number'];
                                }
                                $batchVal  = implode(', ', $batchNumbers);
                                $batchList = implode('||', $batchNumbers);
                            ?>
                                <option
                                    value="<?= htmlspecialchars($pid); ?>"
                                    data-po="<?= htmlspecialchars($po); ?>"
                                    data-so="<?= htmlspecialchars($so); ?>"
                                    data-product="<?= htmlspecialchars($prod); ?>"
                                    data-pcs="<?= (int)$qty; ?>"
                                    data-customer="<?= htmlspecialchars($custName); ?>"
                                    data-batch="<?= htmlspecialchars($batchVal); ?>"
                                    data-batch-list="<?= htmlspecialchars($batchList); ?>"
                                >
                                    [PO: <?= htmlspecialchars($po); ?>] - <?= htmlspecialchars($prod); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Ketik untuk mencari PO atau Nama Produk.</div>
                    </div>

                    <input type="hidden" name="api_product_id" id="api_product_id" value="">

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="po_number" id="po_number" class="form-control bg-light" placeholder="PO" readonly>
                                <label>PO Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="so_number" id="so_number" class="form-control bg-light" placeholder="SO" readonly>
                                <label>SO Number</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="product_name" id="product_name" class="form-control bg-light" placeholder="Product" readonly>
                        <label>Product Name</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Detail Item</label>

                        <div class="form-floating mb-2">
                            <input type="text" name="name_label" id="name_label" class="form-control" placeholder="Customer">
                            <label>Nama Pemilik / Customer</label>

                            <div id="name_choice_wrapper" class="mt-2" style="display:none;">
                                <select id="name_choice" class="form-select form-select-sm"></select>
                                <div class="form-text small">Pilih salah satu nama customer.</div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="text" name="batch_number" id="batch_number" class="form-control" placeholder="Batch">
                                    <label>No. Batch / Lot</label>

                                    <div id="batch_choice_wrapper" class="mt-2" style="display:none;">
                                        <select id="batch_choice" class="form-select form-select-sm"></select>
                                        <div class="form-text small">Pilih No. Batch / Lot.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="number" name="pcs" id="pcs" class="form-control" min="1" value="1" required>
                                    <label>Qty (Pcs)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RFID -->
                    <div class="scan-controls mb-4">
                        <div class="mb-2">
                            <label class="form-label small text-muted fw-bold text-uppercase mb-1">Sumber Reader</label>
                            <select class="form-select form-select-sm" id="reader_key" name="reader">
                                <?php foreach ($visibleReaders as $k => $r): ?>
                                    <option value="<?= htmlspecialchars($k); ?>" <?= ($selectedReader === $k) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($r['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold text-dark">
                                <i class="bi bi-qr-code-scan me-1"></i> RFID Scanner
                            </label>

                            <div class="d-flex align-items-center gap-2">
                                <span id="tagCountBadge" class="badge bg-dark rounded-pill px-3 py-2">0 TAG</span>
                                <span id="scanBadge" class="badge rounded-pill status-idle px-3 py-2">
                                    <i class="bi bi-circle-fill small me-1"></i>
                                    <span id="scanStatusText">Idle</span>
                                </span>
                            </div>
                        </div>

                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" id="btnStartScan" class="btn btn-success">
                                <i class="bi bi-play-fill"></i> Start
                            </button>
                            <button type="button" id="btnStopScan" class="btn btn-secondary" disabled>
                                <i class="bi bi-stop-fill"></i> Stop
                            </button>
                            <button type="button" id="btnClearScan" class="btn btn-dark">
                                <i class="bi bi-trash"></i> Clear
                            </button>
                        </div>

                        <textarea name="rfid_tag" id="rfid_tag"
                                  class="form-control rfid-console"
                                  rows="6"
                                  placeholder="Menunggu scan...&#10;1 baris = 1 tag"
                                  required></textarea>

                        <div class="form-text text-end fst-italic small mt-1">
                            *Tag terdaftar / belum terdaftar akan terlihat di panel kanan.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                        <i class="bi bi-save2 me-2"></i> Simpan Registrasi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- KANAN: HASIL PEMBACAAN LIVE -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-radar me-2"></i>Hasil Pembacaan (Live)
                    </h6>
                    <div class="small text-muted" id="previewCounts">0 total · 0 terdaftar · 0 belum</div>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnPreviewRefresh">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div id="previewContainer" class="border-top p-0">
                    <div class="text-center text-muted py-5 small">
                        <i class="bi bi-upc-scan fs-1 d-block mb-2 opacity-25"></i>
                        Belum ada tag terbaca.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#apiSelect').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: "-- Pilih PO / SO / Product --",
        allowClear: true
    });
});

function parseCustomerNames(raw) {
    raw = (raw || '').trim();
    if (!raw) return [];
    const names = [];
    const regex = /\(\d+\)\s*([^()]+)/g;
    let m;
    while ((m = regex.exec(raw)) !== null) {
        const nm = (m[1] || '').trim();
        if (nm) names.push(nm);
    }
    if (names.length === 0) names.push(raw);
    return names;
}

// Fill form dari Select2
const apiSelect          = document.getElementById('apiSelect');
const apiProductIdEl     = document.getElementById('api_product_id');
const poEl               = document.getElementById('po_number');
const soEl               = document.getElementById('so_number');
const productEl          = document.getElementById('product_name');
const pcsEl              = document.getElementById('pcs');
const nameLabelEl        = document.getElementById('name_label');
const batchEl            = document.getElementById('batch_number');
const nameChoiceWrapEl   = document.getElementById('name_choice_wrapper');
const nameChoiceEl       = document.getElementById('name_choice');
const batchChoiceWrapEl  = document.getElementById('batch_choice_wrapper');
const batchChoiceEl      = document.getElementById('batch_choice');

if (apiSelect) {
    $('#apiSelect').on('change', function () {
        const opt = $(this).find(':selected');

        if (opt.val() === '' || typeof opt.val() === 'undefined') {
            apiProductIdEl.value = '';
            poEl.value = ''; soEl.value = ''; productEl.value = ''; pcsEl.value = 1;
            nameLabelEl.value = ''; batchEl.value = '';
            if (nameChoiceWrapEl) nameChoiceWrapEl.style.display = 'none';
            if (batchChoiceWrapEl) batchChoiceWrapEl.style.display = 'none';
            return;
        }

        const pid          = opt.val() || '';
        const po           = opt.attr('data-po') || '';
        const so           = opt.attr('data-so') || '';
        const prd          = opt.attr('data-product') || '';
        const pcs          = opt.attr('data-pcs') || '';
        const cust         = opt.attr('data-customer') || '';
        const batch        = opt.attr('data-batch') || '';
        const batchListRaw = opt.attr('data-batch-list') || '';

        apiProductIdEl.value = pid;
        poEl.value           = po;
        soEl.value           = so;
        productEl.value      = prd;
        if (pcs) pcsEl.value = pcs;
        if (batch) batchEl.value = batch;

        const candidates = parseCustomerNames(cust);
        if (candidates.length <= 1) {
            if (nameChoiceWrapEl) nameChoiceWrapEl.style.display = 'none';
            if (nameLabelEl) nameLabelEl.value = candidates[0] || '';
        } else {
            if (nameChoiceWrapEl && nameChoiceEl) {
                nameChoiceWrapEl.style.display = 'block';
                nameChoiceEl.innerHTML = '';
                candidates.forEach((nm) => {
                    const o = document.createElement('option');
                    o.value = nm; o.textContent = nm;
                    nameChoiceEl.appendChild(o);
                });
                nameChoiceEl.selectedIndex = 0;
                if (nameLabelEl) nameLabelEl.value = candidates[0];
            }
        }

        let batchCandidates = [];
        if (batchListRaw) batchCandidates = batchListRaw.split('||').map(s => s.trim()).filter(Boolean);

        if (batchCandidates.length <= 1) {
            if (batchChoiceWrapEl) batchChoiceWrapEl.style.display = 'none';
            if (batchEl) batchEl.value = batchCandidates[0] || batch || '';
        } else {
            if (batchChoiceWrapEl && batchChoiceEl) {
                batchChoiceWrapEl.style.display = 'block';
                batchChoiceEl.innerHTML = '';
                batchCandidates.forEach((bn) => {
                    const o = document.createElement('option');
                    o.value = bn; o.textContent = bn;
                    batchChoiceEl.appendChild(o);
                });
                batchChoiceEl.selectedIndex = 0;
                if (batchEl) batchEl.value = batchCandidates[0];
            }
        }
    });
}

if (nameChoiceEl && nameLabelEl) nameChoiceEl.addEventListener('change', () => nameLabelEl.value = nameChoiceEl.value || '');
if (batchChoiceEl && batchEl) batchChoiceEl.addEventListener('change', () => batchEl.value = batchChoiceEl.value || '');

// ==============================
// Scanner Polling + Live Preview
// ==============================
const btnStart      = document.getElementById('btnStartScan');
const btnStop       = document.getElementById('btnStopScan');
const btnClear      = document.getElementById('btnClearScan');

const rfidInput     = document.getElementById('rfid_tag');
const scanBadge     = document.getElementById('scanBadge');
const scanStatusTxt = document.getElementById('scanStatusText');
const tagCountBadge = document.getElementById('tagCountBadge');

const readerSelect  = document.getElementById('reader_key');
function getReaderKey() {
    return (readerSelect && readerSelect.value) ? readerSelect.value : 'zweena_reg';
}

let scanTimer = null;
let previewTimer = null;

function getCurrentTags() {
    if (!rfidInput) return [];
    return rfidInput.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
}

function updateTagCount() {
    const n = getCurrentTags().length;
    if (tagCountBadge) tagCountBadge.textContent = `${n} TAG`;
}

function setVisualStatus(isScanning) {
    if (!scanBadge || !scanStatusTxt || !btnStart || !btnStop) return;
    if (isScanning) {
        scanBadge.classList.remove('status-idle');
        scanBadge.classList.add('status-scanning');
        scanStatusTxt.textContent = 'Scanning...';
        btnStart.disabled = true;
        btnStop.disabled  = false;
        btnStop.classList.remove('btn-secondary');
        btnStop.classList.add('btn-danger');
    } else {
        scanBadge.classList.remove('status-scanning');
        scanBadge.classList.add('status-idle');
        scanStatusTxt.textContent = 'Idle';
        btnStart.disabled = false;
        btnStop.disabled  = true;
        btnStop.classList.remove('btn-danger');
        btnStop.classList.add('btn-secondary');
    }
}

const previewContainer = document.getElementById('previewContainer');
const previewCountsEl  = document.getElementById('previewCounts');
const btnPreviewRefresh = document.getElementById('btnPreviewRefresh');

function renderPreview(items, counts) {
    if (!previewContainer) return;

    const total = counts?.total ?? items.length;
    const reg   = counts?.registered ?? 0;
    const unreg = counts?.unregistered ?? (total - reg);

    if (previewCountsEl) {
        previewCountsEl.textContent = `${total} total · ${reg} terdaftar · ${unreg} belum`;
    }

    if (!items.length) {
        previewContainer.innerHTML = `
            <div class="text-center text-muted py-5 small">
                <i class="bi bi-upc-scan fs-1 d-block mb-2 opacity-25"></i>
                Belum ada tag terbaca.
            </div>`;
        return;
    }

    let html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover align-middle mb-0" style="font-size:.9rem">';
    html += `
        <thead class="table-light">
            <tr>
                <th style="width:26%;">RFID Tag</th>
                <th>Info</th>
                <th style="width:14%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>`;

    items.forEach(row => {
        const ok = !!row.registered;
        const badge = ok
            ? `<span class="badge bg-success">Terdaftar</span>`
            : `<span class="badge bg-danger">Belum</span>`;

        const info = ok
            ? `
                <div class="fw-semibold text-dark">${escapeHtml(row.product_name || '-')}</div>
                <div class="small text-muted">
                    PO: ${escapeHtml(row.po_number || '-')}, SO: ${escapeHtml(row.so_number || '-')}
                    · Batch: ${escapeHtml(row.batch_number || '-')}
                    · Qty: ${Number(row.pcs || 0)}
                </div>
              `
            : `<div class="text-danger fst-italic">Tag belum terdaftar</div>`;

        html += `
            <tr>
                <td class="mono">${escapeHtml(row.tag)}</td>
                <td>${info}</td>
                <td class="text-center">${badge}</td>
            </tr>`;
    });

    html += '</tbody></table></div>';
    previewContainer.innerHTML = html;
}

function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
      .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

async function refreshPreview() {
    const tags = getCurrentTags();
    updateTagCount();

    if (!tags.length) {
        renderPreview([], {total:0, registered:0, unregistered:0});
        return;
    }

    try {
        const res = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tags })
        });

        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            previewContainer.innerHTML = `<div class="p-3 text-danger small">
                Preview gagal dimuat<br>
                Response bukan JSON (kemungkinan redirect login / PHP error). HTTP ${res.status}.<br>
                <div class="mt-2 p-2 bg-light border rounded small mono" style="max-height:140px; overflow:auto;">
                    ${escapeHtml(text.slice(0, 600))}
                </div>
            </div>`;
            return;
        }

        if (!json.success) {
            previewContainer.innerHTML = `<div class="p-3 text-danger small">
                ${escapeHtml(json.message || 'Preview error')}
            </div>`;
            return;
        }

        renderPreview(json.items || [], json.counts || {});
    } catch (err) {
        console.error(err);
        previewContainer.innerHTML = `<div class="p-3 text-danger small">Error koneksi preview.</div>`;
    }
}

function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 300);
}

function startPolling() {
    if (scanTimer) return;
    setVisualStatus(true);

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php?reader=' + encodeURIComponent(getReaderKey()))
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const tags = Array.isArray(data.tags) ? data.tags : (data.epc ? [data.epc] : []);
                if (!tags.length) return;

                let lines = getCurrentTags();
                let added = false;

                tags.forEach(epc => {
                    if (epc && !lines.includes(epc)) { lines.push(epc); added = true; }
                });

                if (added && rfidInput) {
                    rfidInput.value = lines.join("\n");
                    rfidInput.scrollTop = rfidInput.scrollHeight;
                    updateTagCount();
                    schedulePreview();
                }
            })
            .catch(err => console.error('get_latest_rfid error:', err));
    }, 500);
}

function stopPolling() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
    setVisualStatus(false);
    updateTagCount();
}

// START
if (btnStart) {
    btnStart.addEventListener('click', () => {
        fetch('rfid_control.php?action=start&reader=' + encodeURIComponent(getReaderKey()))
            .then(() => startPolling())
            .catch(() => alert('Gagal memulai scanner hardware.'));
    });
}

// STOP
if (btnStop) {
    btnStop.addEventListener('click', () => {
        fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()))
            .then(() => stopPolling())
            .catch(err => console.error(err));
    });
}

// CLEAR
if (btnClear) {
    btnClear.addEventListener('click', async () => {
        const wasScanning = !!scanTimer;
        stopPolling();

        if (rfidInput) rfidInput.value = '';
        updateTagCount();
        renderPreview([], {total:0, registered:0, unregistered:0});

        try { await fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey())); } catch (e) {}

        if (wasScanning) {
            try {
                await fetch('rfid_control.php?action=start&reader=' + encodeURIComponent(getReaderKey()));
                startPolling();
            } catch (e) {
                alert('CLEAR sukses, tapi gagal start ulang scanner.');
            }
        }
    });
}

// Input manual -> preview
if (rfidInput) rfidInput.addEventListener('input', () => { updateTagCount(); schedulePreview(); });

// Ganti reader -> stop & reset
if (readerSelect) {
    readerSelect.addEventListener('change', () => {
        if (scanTimer) {
            fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()))
                .finally(() => stopPolling());
        }
        if (rfidInput) rfidInput.value = '';
        updateTagCount();
        renderPreview([], {total:0, registered:0, unregistered:0});
    });
}

// tombol refresh preview
if (btnPreviewRefresh) btnPreviewRefresh.addEventListener('click', refreshPreview);

// init
setVisualStatus(false);
updateTagCount();
renderPreview([], {total:0, registered:0, unregistered:0});
</script>

<?php include 'layout/footer.php'; ?>
