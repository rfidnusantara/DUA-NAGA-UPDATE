<?php
// layout/header.php
// Dipanggil dari setiap halaman sebelum konten utama

if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = 'WMS RFID';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$username    = $_SESSION['user']['username'] ?? 'Guest';
$role        = $_SESSION['user']['role'] ?? 'inout';

/**
 * ==========================
 * MENU VISIBILITY PER ROLE
 * ==========================
 * admin  : semua menu
 * reg    : dashboard + registrasi + logout
 * inout  : dashboard + in/out + logout
 */
$can = [
    'dashboard'   => true,
    'registrasi'  => in_array($role, ['admin','reg'], true),
    'manage_reg'  => in_array($role, ['admin','reg'], true),
    'inbound'     => in_array($role, ['admin','inout'], true),
    'outbound'    => in_array($role, ['admin','inout'], true),
    'audit'       => in_array($role, ['admin'], true),
    'master'      => in_array($role, ['admin'], true),
    'logout'      => true,
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle); ?> · WMS RFID</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --wms-primary: #0d6efd;
            --wms-primary-soft: #e7f1ff;
            --wms-dark: #111827;
            --wms-sidebar-width: 240px;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            color: #111827;
        }

        .wms-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* ================= SIDEBAR ================= */

        .wms-sidebar {
            width: var(--wms-sidebar-width);
            background: linear-gradient(180deg, #0b5ed7 0%, #042555 100%);
            color: #e5e7eb;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 1030;
            transition: margin-left .25s ease;
        }

        /* Mode tersembunyi: sidebar geser ke kiri */
        body.sidebar-collapsed .wms-sidebar {
            margin-left: calc(-1 * var(--wms-sidebar-width));
        }

        .wms-brand {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .wms-brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.15);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(0,0,0,.15);
        }

        .wms-brand-text {
            line-height: 1.2;
        }

        .wms-brand-text small {
            display: block;
            font-size: .7rem;
            opacity: .8;
        }

        .wms-brand-text span {
            font-weight: 600;
            font-size: .95rem;
            letter-spacing: .06em;
        }

        .wms-sidebar-nav {
            padding: .75rem .5rem 1rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        .wms-sidebar-section-title {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            opacity: .7;
            padding: .75rem .9rem .35rem;
        }

        .wms-sidebar .nav-link {
            color: #d1d5db;
            padding: .45rem .8rem;
            border-radius: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .9rem;
            margin-bottom: .2rem;
            transition: background .15s, color .15s, transform .05s, border-color .15s;
            border: 1px solid transparent;
        }

        .wms-sidebar .nav-link i {
            font-size: 1rem;
        }

        .wms-sidebar .nav-link span {
            flex: 1;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .wms-sidebar .nav-link:hover {
            background: rgba(15,118,255,.25);
            color: #fff;
            transform: translateX(1px);
            border-color: rgba(255,255,255,.08);
        }

        .wms-sidebar .nav-link.active {
            background: #ffffff;
            color: #0b5ed7;
            font-weight: 600;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(15,23,42,.25);
        }

        .wms-sidebar-footer {
            padding: .6rem .9rem .9rem;
            border-top: 1px solid rgba(255,255,255,.08);
            font-size: .75rem;
            opacity: .85;
        }

        /* ================= MAIN AREA ================= */

        .wms-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .wms-topbar {
            height: 56px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .wms-topbar-left {
            display: flex;
            flex-direction: column;
        }

        .wms-topbar-title {
            font-size: .95rem;
            font-weight: 600;
        }

        .wms-topbar-subtitle {
            font-size: .75rem;
            color: #6b7280;
        }

        .wms-topbar-user {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .7rem;
            border-radius: 999px;
            background: #f3f4ff;
            border: 1px solid #e5e7ff;
            font-size: .8rem;
        }

        .wms-topbar-user-avatar {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: #0d6efd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            font-weight: 600;
        }

        .wms-content {
            flex-grow: 1;
            padding: 1.25rem 1.5rem 1.5rem;
        }

        .wms-page-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .breadcrumb-small {
            font-size: .75rem;
        }

        .card-elevated {
            border-radius: .75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 18px rgba(15,23,42,.06);
        }

        /* Tombol toggle sidebar */
        #sidebarToggle {
            border-radius: 999px;
            padding: .3rem .6rem;
            font-size: .9rem;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 768px) {
            .wms-wrapper {
                flex-direction: column;
            }
            .wms-sidebar {
                width: var(--wms-sidebar-width);
                height: 100vh;
                position: fixed;
                left: 0;
                top: 56px; /* di bawah topbar */
            }
            body.sidebar-collapsed .wms-sidebar {
                margin-left: calc(-1 * var(--wms-sidebar-width));
            }
            .wms-main {
                min-height: calc(100vh - 56px);
            }
            .wms-content {
                padding: .75rem .85rem 1rem;
            }
            .wms-page-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="wms-wrapper">

    <!-- ============== SIDEBAR ============== -->
    <aside class="wms-sidebar">
        <div class="wms-brand">
            <div class="wms-brand-logo">
                <span>RF</span>
            </div>
            <div class="wms-brand-text">
                <small>Warehouse System</small>
                <span>WMS RFID</span>
            </div>
        </div>

        <nav class="wms-sidebar-nav">
            <div class="wms-sidebar-section-title">Main</div>

            <?php if ($can['dashboard']): ?>
            <a href="index.php"
               class="nav-link<?= $currentPage === 'index.php' ? ' active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>

            <?php if ($can['registrasi']): ?>
            <a href="registrasi_item.php"
               class="nav-link<?= $currentPage === 'registrasi_item.php' ? ' active' : ''; ?>">
                <i class="bi bi-tags"></i>
                <span>Registrasi Item</span>
            </a>
            <?php endif; ?>

            <?php if ($can['manage_reg']): ?>
            <a href="registrasi_manage.php"
               class="nav-link<?= $currentPage === 'registrasi_manage.php' ? ' active' : ''; ?>">
                <i class="bi bi-gear"></i>
                <span>Manage Registrasi</span>
            </a>
            <?php endif; ?>

            <?php if ($can['inbound']): ?>
            <a href="barang_masuk.php"
               class="nav-link<?= $currentPage === 'barang_masuk.php' ? ' active' : ''; ?>">
                <i class="bi bi-box-arrow-in-down"></i>
                <span>Barang Masuk</span>
            </a>
            <?php endif; ?>

            <?php if ($can['outbound']): ?>
            <a href="barang_keluar.php"
               class="nav-link<?= $currentPage === 'barang_keluar.php' ? ' active' : ''; ?>">
                <i class="bi bi-box-arrow-up"></i>
                <span>Barang Keluar</span>
            </a>
            <?php endif; ?>

            <?php if ($can['audit']): ?>
            <a href="audit_barang.php"
               class="nav-link<?= $currentPage === 'audit_barang.php' ? ' active' : ''; ?>">
                <i class="bi bi-clipboard-data"></i>
                <span>Audit Barang</span>
            </a>
            <?php endif; ?>

            <?php if ($can['master']): ?>
                <div class="wms-sidebar-section-title">Master Data</div>

                <a href="master_gudang.php"
                   class="nav-link<?= $currentPage === 'master_gudang.php' ? ' active' : ''; ?>">
                    <i class="bi bi-building"></i>
                    <span>Lokasi Perusahaan</span>
                </a>

                <a href="master_user.php"
                   class="nav-link<?= $currentPage === 'master_user.php' ? ' active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Manajemen User</span>
                </a>
            <?php endif; ?>

            <div class="wms-sidebar-section-title">Lainnya</div>

            <?php if ($can['logout']): ?>
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="wms-sidebar-footer">
            <div>© <?= date('Y'); ?> WMS RFID</div>
            <div class="small">PT RFID NUSANTARA TEKNOLOGI</div>
        </div>
    </aside>

    <!-- ============== MAIN AREA ============== -->
    <div class="wms-main">
        <header class="wms-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button"
                        id="sidebarToggle"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list"></i>
                </button>

                <div class="wms-topbar-left">
                    <div class="wms-topbar-title">
                        <?= htmlspecialchars($pageTitle); ?>
                    </div>
                    <div class="wms-topbar-subtitle">
                        WMS RFID · Real-time Warehouse Monitoring
                    </div>
                </div>
            </div>

            <div class="wms-topbar-user">
                <div class="wms-topbar-user-avatar">
                    <?php
                    $initial = strtoupper(substr($username, 0, 1));
                    echo htmlspecialchars($initial);
                    ?>
                </div>
                <div>
                    <div style="font-weight:500;"><?= htmlspecialchars($username); ?></div>
                    <div class="small text-muted">Online (<?= htmlspecialchars($role); ?>)</div>
                </div>
            </div>
        </header>

        <main class="wms-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="wms-page-title"><?= htmlspecialchars($pageTitle); ?></div>
                    <div class="breadcrumb-small text-muted">
                        Home / <?= htmlspecialchars($pageTitle); ?>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const btn = document.getElementById('sidebarToggle');
                if (!btn) return;

                try {
                    const saved = localStorage.getItem('wmsSidebarCollapsed');
                    if (saved === '1') document.body.classList.add('sidebar-collapsed');
                } catch (e) {}

                btn.addEventListener('click', function () {
                    document.body.classList.toggle('sidebar-collapsed');
                    try {
                        const collapsed = document.body.classList.contains('sidebar-collapsed');
                        localStorage.setItem('wmsSidebarCollapsed', collapsed ? '1' : '0');
                    } catch (e) {}
                });
            });
            </script>
