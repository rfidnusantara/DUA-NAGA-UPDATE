<?php
// auth.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ==========================
 * 1) CEK LOGIN (wajib)
 * ==========================
 */
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

/**
 * ==========================
 * 2) REFRESH SESSION USER
 * ==========================
 */
function auth_refresh_session_user(): void {
    global $pdo;

    $u = $_SESSION['user'] ?? [];
    $id = (int)($u['id'] ?? 0);
    $username = trim((string)($u['username'] ?? ''));

    if ($id <= 0 && $username === '') {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Ambil data user terbaru dari DB
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT u.*, w.name AS warehouse_name
            FROM users u
            LEFT JOIN warehouses w ON w.id = u.warehouse_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, w.name AS warehouse_name
            FROM users u
            LEFT JOIN warehouses w ON w.id = u.warehouse_id
            WHERE u.username = :u
            LIMIT 1
        ");
        $stmt->execute([':u' => $username]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if (isset($row['is_active']) && (int)$row['is_active'] !== 1) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Normalisasi scope (kalau kosong)
    $role  = (string)($row['role'] ?? 'inout');
    $scope = (string)($row['company_scope'] ?? '');

    if ($scope === '') {
        $scope = in_array($role, ['admin','inout'], true) ? 'ALL' : 'SINGLE';
    }

    $_SESSION['user'] = [
        'id'             => (int)$row['id'],
        'username'       => (string)$row['username'],
        'full_name'      => (string)($row['full_name'] ?? ''),
        'role'           => $role,
        'is_active'      => (int)($row['is_active'] ?? 1),
        'company_scope'  => $scope,
        'warehouse_id'   => isset($row['warehouse_id']) ? (int)$row['warehouse_id'] : null,
        'warehouse_name' => (string)($row['warehouse_name'] ?? ''),
    ];
}

auth_refresh_session_user();

/**
 * ==========================
 * 3) FUNGSI ROLE CHECK
 * ==========================
 */
function require_roles(array $roles): void {
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo "AKSES DITOLAK: Role Anda tidak diizinkan.";
        exit;
    }
}

function require_admin(): void {
    require_roles(['admin']);
}

/**
 * ==========================
 * 4) LIST PT YANG DIIZINKAN (4 PT)
 * ==========================
 */
function allowed_companies(): array {
    return [
        'CV. Zweena Adi Nugraha',
        'PT. Dua Naga Kosmetindo',
        'PT. Phytomed Neo Farma',
        'CV. Indo Naga Food',
    ];
}

function user_company_scope(): string {
    return $_SESSION['user']['company_scope'] ?? 'SINGLE';
}

function user_warehouse_id(): ?int {
    $id = $_SESSION['user']['warehouse_id'] ?? null;
    return ($id === null) ? null : (int)$id;
}

function user_warehouse_name(): string {
    return $_SESSION['user']['warehouse_name'] ?? '';
}

/**
 * ==========================
 * 5) ACL (BATASI HALAMAN PER ROLE)
 *    - InOut TIDAK BOLEH MASUK REGISTRASI
 * ==========================
 */
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$scannerEndpoints = [
    'rfid_control.php',
    'get_latest_rfid.php',
    'preview_rfid_tags.php',
];

$ACL = [
    'admin' => ['*'],

    'inout' => array_merge([
        'index.php',
        'barang_masuk.php',
        'barang_keluar.php',
        'surat_jalan_cetak.php', // penting, kalau tidak OUT akan gagal cetak
        'logout.php',
    ], $scannerEndpoints),

    'reg' => array_merge([
        'index.php',
        'registrasi_item.php',
        'registrasi_manage.php',
        'logout.php',
    ], $scannerEndpoints),
];

// fallback aman jika role tidak dikenali
$role = $_SESSION['user']['role'] ?? 'inout';
if (!isset($ACL[$role])) {
    $ACL[$role] = ['index.php', 'logout.php'];
}

$allowedPages = $ACL[$role];
$ok = in_array('*', $allowedPages, true) || in_array($currentPage, $allowedPages, true);

if (!$ok) {
    // kalau request JSON/AJAX, balas JSON biar JS tidak error
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || stripos($accept, 'application/json') !== false;

    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => "AKSES DITOLAK: role '$role' tidak boleh akses '$currentPage'."
        ]);
        exit;
    }

    http_response_code(403);
    echo "AKSES DITOLAK: role '$role' tidak diizinkan mengakses halaman '$currentPage'.";
    exit;
}
