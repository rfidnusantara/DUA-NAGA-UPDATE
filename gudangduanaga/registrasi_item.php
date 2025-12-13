<?php
// registrasi_manage.php
require_once 'functions.php'; // include config & fungsi API

$pageTitle = 'Registrasi Manage';

// ==========================
// 0. Baca filter perusahaan dari GET
// ==========================
$selectedCompany = trim($_GET['company'] ?? '');

// ==========================
// 0A. Konfigurasi RFID Reader (Multi Gudang)
// ==========================
$RFID_READERS = [
    'zweena_reg'        => ['label' => 'Produksi - CV. Zweena Adi Nugraha (Registrasi)'],
    'dnk_gambiran_reg'  => ['label' => 'PT. Dua Naga Kosmetindo - Gambiran (Registrasi)'],
    'dnk_teblon_reg'    => ['label' => 'PT. Dua Naga Kosmetindo - Teblon (Registrasi)'],
    'central_inout'     => ['label' => 'Gudang Central (Barang Masuk/Keluar)'],
];

$selectedReader = trim($_POST['reader'] ?? $_GET['reader'] ?? 'zweena_reg');
if (!isset($RFID_READERS[$selectedReader])) {
    $selectedReader = 'zweena_reg';
}

// ==========================
// 1. Ambil data dari API
// ==========================
$salesOrdersAll = fetch_sales_orders_from_api(1, 50); // page 1, 50 data

// Jika function mengembalikan ['data'=>...], normalisasi
if (is_array($salesOrdersAll) && isset($salesOrdersAll['data']) && is_array($salesOrdersAll['data'])) {
    $salesOrdersAll = $salesOrdersAll['data'];
}
if (!is_array($salesOrdersAll)) $salesOrdersAll = [];

// Kumpulkan daftar perusahaan dari API (dinamis)
$companyOptions = [];
foreach ($salesOrdersAll as $order) {
    $companyName = $order['company']['name'] ?? '';
    if ($companyName !== '') {
        $companyOptions[$companyName] = $companyName; // unique
    }
}

// Terapkan filter perusahaan ke data sales order
if ($selectedCompany !== '') {
    $salesOrders = array_filter($salesOrdersAll, function ($order) use ($selectedCompany) {
        $companyName = $order['company']['name'] ?? '';
        return $companyName === $selectedCompany;
    });
} else {
    $salesOrders = $salesOrdersAll;
}

// ==========================
// 2. Handle Form Submit (LOGIKA PHP TETAP SAMA)
// ==========================
$error = '';

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
    if (!isset($RFID_READERS[$selectedReader])) $selectedReader = 'zweena_reg';

    if ($apiProductId <= 0 || $productName === '') {
        $error = 'Silakan pilih data dari API (PO/SO/Product) terlebih dahulu.';
    } elseif ($pcs <= 0) {
        $error = 'Pcs harus lebih dari 0.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag tidak boleh kosong (klik Start lalu scan).';
    } else {
        // --- Pisah banyak tag: tiap baris = 1 tag ---
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, function($t){ return $t !== ''; });

        if (empty($tags)) {
            $error = 'RFID Tag tidak valid. Pastikan ada minimal 1 tag.';
        } else {
            // 1) Cek double di FORM (textarea)
            $counts    = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Terdapat RFID Tag double di form (tidak disimpan): ' . implode(', ', $dupInForm);
            } else {
                // 2) Cek double di DATABASE
                $tags         = array_values(array_unique($tags));
                $existingTags = [];
                $newTags      = [];

                $cek = $pdo->prepare("SELECT COUNT(*) FROM rfid_registrations WHERE rfid_tag = :tag");

                foreach ($tags as $tag) {
                    $cek->execute([':tag' => $tag]);
                    $jumlah = (int)$cek->fetchColumn();
                    if ($jumlah > 0) $existingTags[] = $tag;
                    else $newTags[] = $tag;
                }

                if (!empty($existingTags)) {
                    $error = 'RFID Tag berikut sudah terdaftar (double, tidak disimpan): ' . implode(', ', $existingTags);
                } elseif (empty($newTags)) {
                    $error = 'Tidak ada RFID Tag baru untuk disimpan.';
                } else {
                    // 3) Semua OK, simpan ke DB
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

                    header('Location: registrasi_manage.php?success=1&company=' . urlencode($selectedCompany) . '&reader=' . urlencode($selectedReader));
                    exit;
                }
            }
        }
    }
}

// ==========================
// 3. Ambil data registrasi (10 Terakhir)
// ==========================
$stmt = $pdo->query("
    SELECT *
    FROM rfid_registrations
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 4. Tampilkan halaman
// ==========================
include 'layout/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .rfid-console {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        font-size: 0.9rem;
        border: 1px solid #ced4da;
        letter-spacing: 0.5px;
    }
    .rfid-console:focus {
        background-color: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
        100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
    }
    .status-scanning {
        background-color: #198754 !important;
        animation: pulse-green 1.5s infinite;
    }
    .status-idle {
        background-color: #6c757d !important;
    }
    .scan-controls {
        background: #eef2f6;
        border-radius: 10px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
    }
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #ced4da;
    }
</style>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-pencil-square me-2"></i>Form Registrasi Item
                </h6>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>Data berhasil disimpan!</div>
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

                <!-- Filter perusahaan (GET) -->
                <form method="get" class="mb-3">
                    <input type="hidden" name="reader" value="<?= htmlspecialchars($selectedReader); ?>">
                    <label class="form-label small text-muted fw-bold text-uppercase">Filter Perusahaan</label>
                    <select name="company" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Semua Perusahaan --</option>
                        <?php
                        ksort($companyOptions);
                        foreach ($companyOptions as $companyName): ?>
                            <option value="<?= htmlspecialchars($companyName); ?>"
                                <?= ($selectedCompany === $companyName) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($companyName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">
                        Pilih perusahaan terlebih dahulu untuk membatasi daftar PO / SO di bawah.
                    </div>
                </form>

                <!-- Form utama (POST) -->
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
                                    foreach ($order['batches'] as $b) {
                                        if (!empty($b['number'])) $batchNumbers[] = $b['number'];
                                    }
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
                                <div class="form-text small">
                                    Pilih salah satu nama customer, otomatis mengisi kolom di atas.
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="text" name="batch_number" id="batch_number" class="form-control" placeholder="Batch">
                                    <label>No. Batch / Lot</label>

                                    <div id="batch_choice_wrapper" class="mt-2" style="display:none;">
                                        <select id="batch_choice" class="form-select form-select-sm"></select>
                                        <div class="form-text small">
                                            Pilih No. Batch / Lot, otomatis mengisi kolom di atas.
                                        </div>
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

                    <!-- RFID SCANNER -->
                    <div class="scan-controls mb-4">

                        <div class="mb-2">
                            <label class="form-label small text-muted fw-bold text-uppercase mb-1">Sumber Reader</label>
                            <select class="form-select form-select-sm" id="reader_key" name="reader">
                                <?php foreach ($RFID_READERS as $k => $r): ?>
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
                                <!-- TOTAL TAG -->
                                <span id="tagCountBadge" class="badge bg-dark rounded-pill px-3 py-2">0 TAG</span>

                                <!-- STATUS -->
                                <span id="scanBadge" class="badge rounded-pill status-idle px-3 py-2">
                                    <i class="bi bi-circle-fill small me-1"></i>
                                    <span id="scanStatusText">Idle</span>
                                </span>
                            </div>
                        </div>

                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" id="btnStartScan" class="btn btn-success">
                                <i class="bi bi-play-fill"></i> Start Scan
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
                                  placeholder="Menunggu scan...&#10;Tag akan muncul di sini (1 baris per tag)."
                                  required></textarea>
                        <div class="form-text text-end fst-italic small mt-1">
                            *Pastikan reader menyala
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                        <i class="bi bi-save2 me-2"></i> Simpan Registrasi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-list-check me-2"></i>Riwayat Registrasi
                </h6>
                <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-3">No</th>
                            <th>Info Item</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th>RFID Tag</th>
                            <th class="pe-3">Waktu</th>
                        </tr>
                        </thead>
                        <tbody class="border-top-0">
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                    Belum ada data registrasi.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($registrations as $row): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['name_label'] ?: '-'); ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="d-block text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['product_name']); ?>">
                                            <?= htmlspecialchars($row['product_name']); ?>
                                        </span>
                                        <small class="text-muted">Batch: <?= htmlspecialchars($row['batch_number']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill"><?= (int)$row['pcs']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-normal font-monospace">
                                            <?= htmlspecialchars($row['rfid_tag']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3 small text-muted">
                                        <?= date('d/m/Y', strtotime($row['created_at'])); ?><br>
                                        <?= date('H:i', strtotime($row['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// ==============================
// 0. INISIALISASI SELECT2
// ==============================
$(document).ready(function() {
    $('#apiSelect').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: "-- Pilih PO / SO / Product --",
        allowClear: true
    });
});

// ==============================
// Helper: parsing nama customer
// "(1) NAMA1 (2) NAMA2" -> ["NAMA1","NAMA2"]
// ==============================
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

// ==============================
// 1. Isi otomatis dari API ke form
// ==============================
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

        // Customer candidates
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

        // Batch candidates
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

if (nameChoiceEl && nameLabelEl) {
    nameChoiceEl.addEventListener('change', function () {
        nameLabelEl.value = this.value || '';
    });
}
if (batchChoiceEl && batchEl) {
    batchChoiceEl.addEventListener('change', function () {
        batchEl.value = this.value || '';
    });
}

// ==============================
// 2. Kontrol START/STOP + polling RFID
//    + TOTAL COUNT + CLEAR
// ==============================
const btnStart      = document.getElementById('btnStartScan');
const btnStop       = document.getElementById('btnStopScan');
const btnClear      = document.getElementById('btnClearScan');

const rfidInput     = document.getElementById('rfid_tag');
const scanBadge     = document.getElementById('scanBadge');
const scanStatusTxt = document.getElementById('scanStatusText');
const tagCountBadge = document.getElementById('tagCountBadge');

const readerSelect = document.getElementById('reader_key');
function getReaderKey() {
    return (readerSelect && readerSelect.value) ? readerSelect.value : 'zweena_reg';
}

let scanTimer = null;

function getCurrentTags() {
    if (!rfidInput) return [];
    return rfidInput.value
        .split(/\r?\n/)
        .map(s => s.trim())
        .filter(s => s !== '');
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

function startPolling() {
    if (scanTimer) return;
    setVisualStatus(true);

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php?reader=' + encodeURIComponent(getReaderKey()))
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const tags = Array.isArray(data.tags)
                           ? data.tags
                           : (data.epc ? [data.epc] : []);

                if (!tags.length) return;

                let lines = getCurrentTags();
                let added = false;

                tags.forEach(epc => {
                    if (epc && !lines.includes(epc)) {
                        lines.push(epc);
                        added = true;
                    }
                });

                if (added && rfidInput) {
                    rfidInput.value = lines.join("\n");
                    rfidInput.scrollTop = rfidInput.scrollHeight;
                    updateTagCount();
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
            .catch(err => {
                console.error('rfid_control start error:', err);
                alert('Gagal memulai scanner hardware.');
            });
    });
}

// STOP
if (btnStop) {
    btnStop.addEventListener('click', () => {
        fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()))
            .then(() => stopPolling())
            .catch(err => console.error('rfid_control stop error:', err));
    });
}

// CLEAR (hapus textarea + reset backend)
if (btnClear) {
    btnClear.addEventListener('click', async () => {
        const wasScanning = !!scanTimer;

        // stop UI polling
        stopPolling();

        // clear textarea
        if (rfidInput) rfidInput.value = '';
        updateTagCount();

        // STOP backend untuk reset reads_latest.json / read_log.jsonl
        try {
            await fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()));
        } catch (e) {
            console.error(e);
        }

        // kalau sebelumnya scanning, start lagi otomatis
        if (wasScanning) {
            try {
                await fetch('rfid_control.php?action=start&reader=' + encodeURIComponent(getReaderKey()));
                startPolling();
            } catch (e) {
                console.error(e);
                alert('CLEAR sukses, tapi gagal start ulang scanner.');
            }
        }
    });
}

// input manual -> update count
if (rfidInput) {
    rfidInput.addEventListener('input', updateTagCount);
}

// ganti reader saat scanning -> STOP + clear
if (readerSelect) {
    readerSelect.addEventListener('change', () => {
        if (scanTimer) {
            fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()))
                .finally(() => stopPolling());
        }
        if (rfidInput) rfidInput.value = '';
        updateTagCount();
    });
}

// State awal
setVisualStatus(false);
updateTagCount();
</script>

<?php include 'layout/footer.php'; ?>
