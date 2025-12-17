<?php
// login.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u AND is_active = 1 LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (string)$user['password'] !== (string)$password) {
            $error = 'Kredensial tidak valid atau akun dinonaktifkan.';
        } else {
            $_SESSION['user'] = [
                'id'        => (int)$user['id'],
                'username'  => (string)$user['username'],
                'full_name' => (string)($user['full_name'] ?? ''),
                'role'      => (string)($user['role'] ?? 'inout'),
            ];
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Login WMS · RFID Nusantara';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --font-main: 'Poppins', sans-serif;
            --primary-blue: #2563eb; 
            --primary-hover: #1d4ed8;
            --bg-body: #f1f5f9;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden; 
            position: relative;
        }

        /* --- Dynamic Background --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(37, 99, 235, 0.2);
            top: -100px; left: -100px;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: rgba(56, 189, 248, 0.2);
            bottom: -50px; right: -50px;
            animation-delay: -5s;
        }
        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 50px); }
        }

        /* Card Container */
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 1000px;
            margin: 20px;
            border-radius: 24px;
            box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.1);
            display: flex;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.5);
            animation: slideUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        /* --- ANIMASI SCANNER --- */
        .scanner-wrapper {
            position: relative;
            width: 180px; height: 180px; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: center;
        }
        .box-icon { font-size: 8rem; color: rgba(255,255,255,0.2); position: relative; z-index: 1; }
        .laser-scan {
            position: absolute; width: 100%; height: 4px;
            background: #fff; box-shadow: 0 0 15px #fff, 0 0 30px var(--primary-blue);
            z-index: 2; animation: scanMove 3s ease-in-out infinite;
            border-radius: 50%; opacity: 0.8;
        }
        .rfid-signal {
            position: absolute; top: -20px; right: -20px;
            font-size: 3rem; color: #fff; animation: signalPulse 2s infinite;
        }
        @keyframes scanMove {
            0%, 100% { top: 20%; opacity: 0; width: 60%; left: 20%;}
            50% { top: 80%; opacity: 1; width: 100%; left: 0;}
        }
        @keyframes signalPulse {
            0% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 1; text-shadow: 0 0 20px #fff; }
            100% { transform: scale(0.8); opacity: 0.5; }
        }

        /* Left Panel */
        .panel-visual {
            flex: 0.9;
            background: linear-gradient(135deg, var(--primary-blue), #3b82f6);
            padding: 40px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: white; text-align: center; position: relative; overflow: hidden;
        }
        .panel-visual::before {
            content: ''; position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.15) 1px, transparent 1px);
            background-size: 20px 20px; opacity: 0.4;
        }
        .visual-title { font-weight: 700; font-size: 2rem; margin-bottom: 10px; position: relative; z-index: 2; line-height: 1.2; }
        .visual-desc { opacity: 0.9; font-size: 0.95rem; max-width: 80%; position: relative; z-index: 2; }

        /* Right Panel */
        .panel-form { flex: 1.2; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
        .form-head h3 { font-weight: 700; color: var(--text-dark); margin-bottom: 5px; }
        .form-head p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 25px; }

        /* Inputs & Toggle Password */
        .form-floating > .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding-left: 15px;
            padding-right: 45px; /* Space for eye icon */
        }
        .form-floating > .form-control:focus {
            background: #fff;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .form-floating label { color: #94a3b8; padding-left: 15px; }

        /* Icon Mata (Show Password) */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        .password-toggle:hover { color: var(--primary-blue); }
        
        /* Button */
        .btn-action {
            width: 100%; padding: 14px;
            border-radius: 12px; border: none;
            background: var(--primary-blue);
            color: white; font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); }

        /* Partners Grid */
        .partners-grid-large {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 30px 0;
        }
        .partner-item {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 15px; display: flex; align-items: center; justify-content: center; height: 90px;
            transition: all 0.3s ease;
        }
        .partner-item:hover {
            border-color: var(--primary-blue); box-shadow: 0 4px 15px rgba(37, 99, 235, 0.1); background: #fff;
        }
        .partner-item img {
            max-width: 100%; max-height: 100%; object-fit: contain; filter: grayscale(1) opacity(0.7); transition: all 0.3s;
        }
        .partner-item:hover img { filter: grayscale(0) opacity(1); }

        /* Footer */
        .dev-branding { padding-top: 20px; border-top: 1px dashed #cbd5e1; text-align: center; }
        .dev-text { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .dev-name { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; justify-content: center; gap: 6px; }
        .dev-name i { color: var(--primary-blue); }

        @media (max-width: 900px) {
            .login-card { flex-direction: column; max-width: 450px; }
            .panel-visual { padding: 30px; flex: 1; }
            .panel-form { padding: 30px; flex: 1; }
            .partners-grid-large { gap: 10px; margin: 25px 0; }
            .partner-item { height: 80px; padding: 10px; }
        }

        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <div class="panel-visual">
            <div class="scanner-wrapper">
                <i class="bi bi-box-seam-fill box-icon"></i>
                <div class="laser-scan"></div>
                <i class="bi bi-broadcast rfid-signal"></i>
            </div>
            <h2 class="visual-title">Smart WMS<br>System</h2>
            <p class="visual-desc">Sistem manajemen pergudangan terintegrasi berbasis teknologi RFID.</p>
        </div>

        <div class="panel-form">
            <div class="form-head">
                <h3>Welcome Back!</h3>
                <p>Silakan login untuk mengakses dashboard.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 mb-3" style="font-size: 0.9rem; border-radius: 10px;">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="uInput" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <label for="uInput">Username ID</label>
                </div>
                
                <div class="form-floating mb-4 position-relative">
                    <input type="password" class="form-control" id="pInput" name="password" placeholder="Password" required>
                    <label for="pInput">Password</label>
                    <span id="togglePass" class="password-toggle">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>

                <button type="submit" class="btn-action">
                    Sign In <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>

            <div class="partners-grid-large">
                <div class="partner-item" title="Zweena"><img src="assets/zweena1.png" alt="Zweena"></div>
                <div class="partner-item" title="Neo Farma"><img src="assets/neo1.png" alt="Neo"></div>
                <div class="partner-item" title="Dua Naga"><img src="assets/naga.png" alt="Naga"></div>
                <div class="partner-item" title="Indo Naga"><img src="assets/indo1.png" alt="Indo"></div>
            </div>

            <div class="dev-branding">
                <div class="dev-text">System Developed & Managed By</div>
                <div class="dev-name">
                    <i class="bi bi-patch-check-fill"></i> PT RFID NUSANTARA TEKNOLOGI
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const togglePass = document.getElementById('togglePass');
        const pInput = document.getElementById('pInput');
        const icon = togglePass.querySelector('i');

        togglePass.addEventListener('click', function () {
            // Cek tipe saat ini (password atau text)
            const type = pInput.getAttribute('type') === 'password' ? 'text' : 'password';
            pInput.setAttribute('type', type);
            
            // Ganti ikon mata
            if(type === 'text'){
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash'); // Mata dicoret (hide)
            } else {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye'); // Mata biasa (show)
            }
        });
    </script>
</body>
</html>