<?php
// barang_masuk.php
require_once 'functions.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle   = 'Barang Masuk (Inbound)';

// ==========================
// 0) Ambil user login + role
// ==========================
$currentUserArr = $_SESSION['user'] ?? null;
if (!$currentUserArr) {
    header('Location: login.php');
    exit;
}

$currentUser = $currentUserArr['username'] ?? 'System';
$userRole    = $currentUserArr['role'] ?? 'user';

// ==========================
// 0A) Hak akses halaman
// ==========================
if (!in_array($userRole, ['admin', 'inout'], true)) {
    http_response_code(403);
    echo "AKSES DITOLAK: Role Anda tidak diizinkan untuk menu Barang Masuk.";
    exit;
}

// ==========================
// 1) Reader untuk IN/OUT hanya Central
// ==========================
$selectedReader = 'central_inout'; // dipaksa central

// ==========================
// 2) Ambil daftar perusahaan (HANYA 4 PT)
// ==========================
$allowedCompanies = [
    'CV. Zweena Adi Nugraha',
    'PT. Dua Naga Kosmetindo',
    'PT. Phytomed Neo Farma',
    'CV. Indo Naga Food',
];

$placeholders = implode(',', array_fill(0, count($allowedCompanies), '?'));
$stmt = $pdo->prepare("
    SELECT id, name, code
    FROM warehouses
    WHERE name IN ($placeholders)
    ORDER BY name ASC
");
$stmt->execute($allowedCompanies);
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map id valid untuk validasi submit
$allowedWarehouseIds = array_map(fn($w) => (int)$w['id'], $warehouses);

// ==========================
// 3) Handle Form Submit
// ==========================
$error = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw     = trim($_POST['rfid_tags'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($warehouseId <= 0 || !in_array($warehouseId, $allowedWarehouseIds, true)) {
        $error = 'Silakan pilih Perusahaan tujuan yang valid.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag kosong. Silakan scan tag terlebih dahulu.';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'Format RFID tidak valid.';
        } else {
            // Cek duplikasi di form
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Duplikasi tag di input (batalkan): ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags)); // unikkan

                $getReg = $pdo->prepare("
                    SELECT *
                    FROM rfid_registrations
                    WHERE rfid_tag = :tag
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $movementInsert = $pdo->prepare("
                    INSERT INTO stock_movements
                        (rfid_tag, registration_id, warehouse_id, movement_type, movement_time, created_by, notes)
                    VALUES
                        (:rfid_tag, :registration_id, :warehouse_id, 'IN', :movement_time, :created_by, :notes)
                ");

                $updateActive = $pdo->prepare("
                    UPDATE rfid_registrations
                    SET is_active = 1
                    WHERE id = :id
                ");

                $notRegistered = [];
                $now = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
                    ->format('Y-m-d H:i:s');

                $pdo->beginTransaction();
                try {
                    foreach ($tags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);

                        if (!$reg) {
                            $notRegistered[] = $tag;
                            continue;
                        }

                        $movementInsert->execute([
                            ':rfid_tag'        => $tag,
                            ':registration_id' => $reg['id'],
                            ':warehouse_id'    => $warehouseId,
                            ':movement_time'   => $now,
                            ':created_by'      => $currentUser,
                            ':notes'           => $notes,
                        ]);

                        $updateActive->execute([':id' => $reg['id']]);
                    }

                    if (!empty($notRegistered)) {
                        $pdo->rollBack();
                        $error = 'Gagal! Ada tag yang belum terdaftar: '
                               . implode(', ', $notRegistered)
                               . '. Harap registrasi dahulu.';
                    } else {
                        $pdo->commit();
                        $successMsg = 'Sukses! ' . count($tags) . ' item berhasil masuk ke stok.';

                        // Reset form
                        $_POST['rfid_tags'] = '';
                        $_POST['notes']     = '';
                        $_POST['warehouse_id'] = '';
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Database Error: ' . $e->getMessage();
                }
            }
        }
    }
}

include 'layout/header.php';
?>

<style>
    .rfid-console {
        background-color: #212529;
        color: #00ff00;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.95rem;
        border: 2px solid #343a40;
    }
    .rfid-console:focus {
        background-color: #212529;
        color: #00ff00;
        border-color: #0d6efd;
        box-shadow: none;
    }
    .scanner-box {
        background: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 8px;
        padding: 15px;
    }
    @keyframes pulse-red {
        0%   { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70%  { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .status-scanning {
        animation: pulse-red 2s infinite;
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
        color: white !important;
    }
    #previewContainer {
        min-height: 120px;
        max-height: 420px;
        overflow-y: auto;
        font-size: 0.85rem;
    }
</style>

<div class="row g-4">
    <div class="col-lg-5 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-box-arrow-in-down me-2"></i>
                    Form Barang Masuk
                </h6>
                <small class="text-muted">Akses: <b><?= htmlspecialchars($userRole); ?></b> | Reader: <b>Central</b></small>
            </div>
            <div class="card-body">

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                        <div><?= htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="form-floating mb-3">
                        <select name="warehouse_id" id="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Lokasi Perusahaan --</option>
                            <?php foreach ($warehouses as $g): ?>
                                <option value="<?= (int)$g['id']; ?>"
                                    <?= (isset($_POST['warehouse_id']) && (int)$_POST['warehouse_id'] === (int)$g['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($g['name']); ?> (<?= htmlspecialchars($g['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="warehouse_id">Perusahaan Tujuan</label>
                    </div>

                    <div class="scanner-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold small text-uppercase text-muted">
                                RFID Scanner Control (Central)
                            </label>

                            <div class="d-flex align-items-center gap-2">
                                <span id="tagCountBadge" class="badge bg-dark rounded-pill px-3">0 TAG</span>
                                <span id="scanBadge" class="badge bg-secondary rounded-pill px-3">IDLE</span>
                            </div>
                        </div>

                        <!-- reader dipaksa central -->
                        <input type="hidden" id="reader_key" name="reader" value="central_inout">

                        <div class="btn-group w-100 mb-2">
                            <button type="button" id="btnStartScan" class="btn btn-outline-success fw-bold">
                                <i class="bi bi-play-fill"></i> START
                            </button>
                            <button type="button" id="btnStopScan" class="btn btn-outline-danger fw-bold" disabled>
                                <i class="bi bi-stop-fill"></i> STOP
                            </button>
                            <button type="button" id="btnClearScan" class="btn btn-dark fw-bold">
                                <i class="bi bi-trash"></i> CLEAR
                            </button>
                        </div>

                        <textarea name="rfid_tags" id="rfid_tags"
                                  class="form-control rfid-console"
                                  rows="6"
                                  placeholder="> Menunggu input scanner..."
                                  required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        <div class="text-end mt-1">
                            <small class="text-muted fst-italic">
                                Pastikan kursor aktif di area hitam saat scanning.
                            </small>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text"
                               name="notes"
                               class="form-control"
                               id="notes"
                               placeholder="Catatan"
                               value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>">
                        <label for="notes">Catatan / No. Surat Jalan (Opsional)</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i> SIMPAN BARANG MASUK
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-muted">
                    <i class="bi bi-eye me-2"></i>
                    Live Preview Data Scan
                </h6>
                <small class="text-muted" style="font-size: 0.75rem;">
                    Otomatis update saat tag bertambah
                </small>
            </div>
            <div class="card-body p-0">
                <div id="previewContainer" class="border-top bg-light p-0">
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-upc-scan fs-3 d-block mb-2"></i>
                        Belum ada tag terbaca
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const btnStart   = document.getElementById('btnStartScan');
const btnStop    = document.getElementById('btnStopScan');
const btnClear   = document.getElementById('btnClearScan');

const rfidArea   = document.getElementById('rfid_tags');
const scanBadge  = document.getElementById('scanBadge');
const tagCountBadge = document.getElementById('tagCountBadge');
const previewContainer = document.getElementById('previewContainer');

function getReaderKey() { return 'central_inout'; }

let scanTimer   = null;
let previewTimer = null;

function getCurrentTags() {
    if (!rfidArea) return [];
    return rfidArea.value
        .split(/\r?\n/)
        .map(s => s.trim())
        .filter(s => s !== '');
}

function updateTagCount() {
    const n = getCurrentTags().length;
    if (tagCountBadge) tagCountBadge.textContent = `${n} TAG`;
}

function setScanningState(isScanning) {
    if (isScanning) {
        scanBadge.textContent = 'SCANNING...';
        scanBadge.className   = 'badge rounded-pill px-3 status-scanning';
        btnStart.disabled     = true;
        btnStop.disabled      = false;
        btnStart.classList.replace('btn-outline-success', 'btn-success');
    } else {
        scanBadge.textContent = 'IDLE';
        scanBadge.className   = 'badge bg-secondary rounded-pill px-3';
        btnStart.disabled     = false;
        btnStop.disabled      = true;
        btnStart.classList.replace('btn-success', 'btn-outline-success');
    }
}

function refreshPreview() {
    if (!previewContainer) return;
    const tags = getCurrentTags();

    if (tags.length === 0) {
        previewContainer.innerHTML = `
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-upc-scan fs-3 d-block mb-2"></i>
                Belum ada tag terbaca
            </div>`;
        return;
    }

    fetch('preview_rfid_tags.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tags: tags })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !Array.isArray(data.items)) {
            previewContainer.innerHTML =
                '<div class="p-2 text-danger small">Gagal memuat preview.</div>';
            return;
        }

        let html = '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered mb-0 small align-middle">';
        html += '<thead class="table-light text-muted"><tr>' +
                '<th style="width: 28%;">Tag</th>' +
                '<th>Produk</th>' +
                '<th style="width: 12%; text-align:center;">Status</th>' +
                '</tr></thead><tbody>';

        data.items.forEach(row => {
            const registered = !!row.registered;
            const statusBadge = registered
                ? '<span class="badge bg-success">Valid</span>'
                : '<span class="badge bg-danger">Unknown</span>';
            const productName = registered
                ? (row.product_name || '-')
                : '<span class="text-danger fst-italic">Tidak terdaftar</span>';

            html += `<tr>
                <td class="font-monospace">${row.tag}</td>
                <td>${productName}</td>
                <td class="text-center">${statusBadge}</td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        previewContainer.innerHTML = html;
    })
    .catch(err => {
        console.error(err);
        previewContainer.innerHTML =
            '<div class="p-2 text-danger small">Error koneksi preview.</div>';
    });
}

function schedulePreviewUpdate() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 500);
}

function startPolling() {
    if (scanTimer) return;
    setScanningState(true);

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

                if (added) {
                    rfidArea.value      = lines.join("\n");
                    rfidArea.scrollTop  = rfidArea.scrollHeight;
                    updateTagCount();
                    schedulePreviewUpdate();
                }
            })
            .catch(err => console.error('Polling error:', err));
    }, 500);
}

function stopPolling() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
    setScanningState(false);
    updateTagCount();
}

btnStart.addEventListener('click', () => {
    fetch('rfid_control.php?action=start&reader=' + encodeURIComponent(getReaderKey()))
        .then(() => startPolling())
        .catch(() => alert('Gagal memulai hardware scanner'));
});

btnStop.addEventListener('click', () => {
    fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()))
        .then(() => stopPolling())
        .catch(err => console.error(err));
});

btnClear.addEventListener('click', async () => {
    const wasScanning = !!scanTimer;

    stopPolling();

    if (rfidArea) rfidArea.value = '';
    updateTagCount();
    schedulePreviewUpdate();

    try {
        await fetch('rfid_control.php?action=stop&reader=' + encodeURIComponent(getReaderKey()));
    } catch (e) {
        console.error(e);
    }

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

rfidArea.addEventListener('input', () => {
    updateTagCount();
    schedulePreviewUpdate();
});

setScanningState(false);
updateTagCount();
schedulePreviewUpdate();
</script>

<?php include 'layout/footer.php'; ?>
