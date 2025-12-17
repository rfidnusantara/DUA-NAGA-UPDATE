<?php
// master_user.php (REVISI: TANPA HASH, pakai kolom `password`, role inout/reg + PT per user reg)
require_once 'auth.php';
require_admin();

$pageTitle = 'Manajemen User';

$error = '';
$success = '';

// ==========================
// 0) PT yang diizinkan
// ==========================
$ALLOWED_COMPANIES = [
    'CV. Zweena Adi Nugraha',
    'PT. Dua Naga Kosmetindo',
    'PT. Phytomed Neo Farma',
    'CV. Indo Naga Food',
];

// Ambil warehouses hanya 4 PT ini
$placeholders = implode(',', array_fill(0, count($ALLOWED_COMPANIES), '?'));
$stmtW = $pdo->prepare("SELECT id, name FROM warehouses WHERE name IN ($placeholders) ORDER BY name ASC");
$stmtW->execute($ALLOWED_COMPANIES);
$warehouseList = $stmtW->fetchAll(PDO::FETCH_ASSOC);

$warehouseMap = [];
foreach ($warehouseList as $w) $warehouseMap[(int)$w['id']] = $w['name'];

// ==========================
// 1) Handle POST (insert/update)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = (int)($_POST['id'] ?? 0);
    $username     = trim($_POST['username'] ?? '');
    $full_name    = trim($_POST['full_name'] ?? '');
    $password     = (string)($_POST['password'] ?? '');
    $role         = (string)($_POST['role'] ?? 'inout');
    $is_active    = isset($_POST['is_active']) ? 1 : 0;
    $warehouse_id = $_POST['warehouse_id'] ?? null;

    // kompatibilitas role lama "user" -> "inout"
    if ($role === 'user') $role = 'inout';

    // normalize warehouse_id
    $warehouse_id = ($warehouse_id === '' || $warehouse_id === null) ? null : (int)$warehouse_id;

    // Validasi dasar
    if ($username === '' || $full_name === '') {
        $error = 'Username dan nama lengkap wajib diisi.';
    } elseif (!in_array($role, ['admin','inout','reg'], true)) {
        $error = 'Role tidak valid.';
    } else {
        // aturan perusahaan
        if ($role === 'reg') {
            if (empty($warehouse_id) || !isset($warehouseMap[$warehouse_id])) {
                $error = 'User Registrasi (REG) wajib memilih 1 Perusahaan (PT) yang valid.';
            }
        } else {
            // admin/inout tidak dikunci PT
            $warehouse_id = null;
        }
    }

    if ($error === '') {
        if ($id > 0) {
            // UPDATE
            if ($password !== '') {
                // TANPA HASH: simpan plain text ke kolom `password`
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username=:u, full_name=:n, password=:p, role=:r, is_active=:a, warehouse_id=:wid
                    WHERE id=:id
                ");
                $stmt->execute([
                    ':u'   => $username,
                    ':n'   => $full_name,
                    ':p'   => $password,
                    ':r'   => $role,
                    ':a'   => $is_active,
                    ':wid' => $warehouse_id,
                    ':id'  => $id
                ]);
            } else {
                // password tidak diubah
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username=:u, full_name=:n, role=:r, is_active=:a, warehouse_id=:wid
                    WHERE id=:id
                ");
                $stmt->execute([
                    ':u'   => $username,
                    ':n'   => $full_name,
                    ':r'   => $role,
                    ':a'   => $is_active,
                    ':wid' => $warehouse_id,
                    ':id'  => $id
                ]);
            }
            $success = 'Data user berhasil diperbarui.';
        } else {
            // INSERT
            if ($password === '') {
                $error = 'Password wajib diisi untuk user baru.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, full_name, password, role, is_active, warehouse_id)
                    VALUES (:u, :n, :p, :r, :a, :wid)
                ");
                try {
                    $stmt->execute([
                        ':u'   => $username,
                        ':n'   => $full_name,
                        ':p'   => $password, // TANPA HASH
                        ':r'   => $role,
                        ':a'   => $is_active,
                        ':wid' => $warehouse_id
                    ]);
                    $success = 'User baru berhasil ditambahkan.';
                } catch (PDOException $e) {
                    if (stripos($e->getMessage(), 'Duplicate') !== false) {
                        $error = 'Username sudah digunakan. Pilih username lain.';
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }
}

// ==========================
// 2) Handle Delete
// ==========================
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        if (!empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === $delId) {
            $error = 'Tidak bisa menghapus user yang sedang login.';
        } else {
            $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $del->execute([':id' => $delId]);
            $success = 'User berhasil dihapus.';
        }
    }
}

// ==========================
// 3) Get Data (join nama PT)
// ==========================
$stmt = $pdo->query("
    SELECT u.*, w.name AS warehouse_name
    FROM users u
    LEFT JOIN warehouses w ON w.id = u.warehouse_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 4) Edit Mode
// ==========================
$editData = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ((int)$u['id'] === $editId) {
            $editData = $u;
            break;
        }
    }
}

include 'layout/header.php';
?>

<style>
    .card-modern{border:none;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.05)}
    .avatar-initials{width:38px;height:38px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.9rem;margin-right:10px;text-transform:uppercase}
    .badge-soft-primary{background:rgba(13,110,253,.1);color:#0d6efd}
    .badge-soft-secondary{background:rgba(108,117,125,.1);color:#6c757d}
    .badge-soft-success{background:rgba(25,135,84,.1);color:#198754}
    .badge-soft-danger{background:rgba(220,53,69,.1);color:#dc3545}
    .btn-action{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:0}
    .bg-color-1{background:#4e73df}.bg-color-2{background:#1cc88a}.bg-color-3{background:#36b9cc}
    .bg-color-4{background:#f6c23e}.bg-color-5{background:#e74a3b}.bg-color-6{background:#858796}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Manajemen User</h4>
        <small class="text-muted">Role: INOUT (Gudang) / REG (Registrasi per PT) / ADMIN</small>
    </div>
    <?php if ($editData): ?>
        <a href="master_user.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi <?= $editData ? 'bi-person-gear' : 'bi-person-plus'; ?> me-2"></i>
                    <?= $editData ? 'Edit Data User' : 'Tambah User Baru'; ?>
                </h6>
            </div>
            <div class="card-body">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center small py-2">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success d-flex align-items-center small py-2">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?= htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off" id="userForm">
                    <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0); ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($editData['username'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-card-heading"></i></span>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($editData['full_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">
                            Password <?= $editData ? '<span class="fw-normal text-muted">(Isi jika ingin ubah)</span>' : ''; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="inputPassword" class="form-control" placeholder="******">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">Password disimpan TANPA hash (plain text) sesuai permintaan.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Role</label>
                            <?php
                                $editRole = (string)($editData['role'] ?? 'inout');
                                if ($editRole === 'user') $editRole = 'inout';
                            ?>
                            <select name="role" class="form-select" id="roleSelect">
                                <option value="inout" <?= $editRole === 'inout' ? 'selected' : ''; ?>>INOUT (Gudang)</option>
                                <option value="reg"   <?= $editRole === 'reg' ? 'selected' : ''; ?>>REG (Registrasi)</option>
                                <option value="admin" <?= $editRole === 'admin' ? 'selected' : ''; ?>>ADMIN</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Status Akun</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active"
                                       <?= !isset($editData['is_active']) || (int)$editData['is_active'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>

                    <!-- Perusahaan hanya untuk role REG -->
                    <div class="mb-3" id="companyWrap" style="display:none;">
                        <label class="form-label small fw-bold text-muted">Perusahaan (PT) untuk REG</label>
                        <select name="warehouse_id" class="form-select" id="warehouseSelect">
                            <option value="">-- Pilih Perusahaan --</option>
                            <?php
                                $editWid = (int)($editData['warehouse_id'] ?? 0);
                                foreach ($warehouseList as $w):
                                    $wid = (int)$w['id'];
                                    $wn  = $w['name'];
                            ?>
                                <option value="<?= $wid; ?>" <?= ($editWid === $wid) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($wn); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            User REG hanya muncul 1 PT ini di menu registrasi dan reader ikut filter PT.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i>
                        <?= $editData ? 'Simpan Perubahan' : 'Buat User'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel user -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark">
                    <i class="bi bi-people me-2"></i>Daftar Pengguna
                </h6>
                <span class="badge bg-light text-dark border"><?= count($users); ?> Users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>PT (khusus REG)</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-emoji-frown fs-1 d-block mb-2 opacity-50"></i>
                                    Belum ada data user.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $bgColors = ['bg-color-1','bg-color-2','bg-color-3','bg-color-4','bg-color-5','bg-color-6']; ?>
                            <?php foreach ($users as $index => $u): ?>
                                <?php
                                    $initial = strtoupper(substr($u['full_name'], 0, 1));
                                    $colorClass = $bgColors[$index % count($bgColors)];
                                    $r = (string)$u['role'];
                                    if ($r === 'user') $r = 'inout';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials <?= $colorClass; ?>"><?= $initial; ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']); ?></div>
                                                <div class="small text-muted">@<?= htmlspecialchars($u['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($r === 'admin'): ?>
                                            <span class="badge badge-soft-primary rounded-pill px-3"><i class="bi bi-shield-lock-fill me-1"></i> Admin</span>
                                        <?php elseif ($r === 'reg'): ?>
                                            <span class="badge badge-soft-secondary rounded-pill px-3"><i class="bi bi-tags me-1"></i> Reg</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary rounded-pill px-3"><i class="bi bi-box-arrow-in-down me-1"></i> InOut</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= ($r === 'reg') ? htmlspecialchars($u['warehouse_name'] ?? '-') : '-'; ?></td>
                                    <td>
                                        <?php if ((int)$u['is_active'] === 1): ?>
                                            <span class="badge badge-soft-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="master_user.php?edit=<?= (int)$u['id']; ?>" class="btn btn-action btn-outline-warning" data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil-square small"></i>
                                            </a>
                                            <a href="master_user.php?delete=<?= (int)$u['id']; ?>" class="btn btn-action btn-outline-danger ms-1"
                                               onclick="return confirm('Hapus user <?= htmlspecialchars($u['username']); ?>?');" data-bs-toggle="tooltip" title="Hapus">
                                                <i class="bi bi-trash3 small"></i>
                                            </a>
                                        </div>
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

<script>
    // Toggle Password Visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#inputPassword');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }

    // Role -> show/hide company
    const roleSelect = document.getElementById('roleSelect');
    const companyWrap = document.getElementById('companyWrap');
    const warehouseSelect = document.getElementById('warehouseSelect');

    function syncCompanyVisibility() {
        const r = roleSelect ? roleSelect.value : 'inout';
        if (!companyWrap) return;

        if (r === 'reg') {
            companyWrap.style.display = 'block';
        } else {
            companyWrap.style.display = 'none';
            if (warehouseSelect) warehouseSelect.value = '';
        }
    }
    if (roleSelect) {
        roleSelect.addEventListener('change', syncCompanyVisibility);
        syncCompanyVisibility();
    }

    // Init Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
</script>

<?php include 'layout/footer.php'; ?>
