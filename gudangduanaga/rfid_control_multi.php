<?php
// rfid_control.php (Multi Reader)
// API kecil untuk Start/Stop backend RFID per reader:
//
//   ?action=start&reader=central_inout
//   ?action=stop&reader=zweena_reg
//   ?action=status&reader=dnk_gambiran_reg
//
// Penting:
// - Folder kontrol & latest dipisah per reader:
//     static_files/<reader>/rfid_control.json
//     static_files/<reader>/reads_latest.json
//
// Backend Python yang direkomendasikan:
// - run_rfid_multi.py --reader <key>
//   (kalau belum ada, sistem akan fallback ke run_rfid.py)

header('Content-Type: application/json');

// -----------------------------
// 1) Ambil reader key
// -----------------------------
$reader = $_GET['reader'] ?? $_POST['reader'] ?? 'central_inout';
$reader = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reader);
if ($reader === '') $reader = 'central_inout';

// -----------------------------
// 2) File kontrol per reader
// -----------------------------
$ctrlBase = __DIR__ . '/static_files';
$ctrlDir  = $ctrlBase . '/' . $reader;
$ctrlPath = $ctrlDir . '/rfid_control.json';

// Pastikan folder ada
if (!is_dir($ctrlDir)) {
    @mkdir($ctrlDir, 0777, true);
}

// Baca state sekarang (default: enabled=false)
$state = ['enabled' => false];
if (file_exists($ctrlPath)) {
    $json = file_get_contents($ctrlPath);
    if ($json !== false && trim($json) !== '') {
        $tmp = json_decode($json, true);
        if (is_array($tmp)) {
            $state = array_merge($state, $tmp);
        }
    }
}

// -----------------------------
// 3) Konfigurasi Python & Script
// -----------------------------
// Path ke Python di Windows (hasil "where python")
$pythonPath = '/usr/bin/python3';

// Prioritas script:
// 1) run_rfid_multi.py (disarankan)
// 2) run_rfid_<reader>.py (opsional, kalau Anda pecah per file)
// 3) run_rfid.py (fallback, single reader)
$multiScript = __DIR__ . DIRECTORY_SEPARATOR . 'run_rfid_multi.py';
$perReader   = __DIR__ . DIRECTORY_SEPARATOR . ('run_rfid_' . $reader . '.py');
$fallback    = __DIR__ . DIRECTORY_SEPARATOR . 'run_rfid.py';

$scriptPath  = null;
$args        = [];

if (file_exists($multiScript)) {
    $scriptPath = $multiScript;
    $args = ['--reader', $reader];
} elseif (file_exists($perReader)) {
    $scriptPath = $perReader;
} elseif (file_exists($fallback)) {
    $scriptPath = $fallback;
} else {
    $scriptPath = null;
}

// -----------------------------
// 4) Ambil action dari request
// -----------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

// -----------------------------
// Helper: jalankan Python di background
// -----------------------------
function start_python_backend($pythonPath, $scriptPath, $args = [])
{
    if (!$scriptPath || !file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Script Python tidak ditemukan'];
    }

    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }

    if ($isWindows) {
        // Windows: gunakan "start /B" supaya jalan di background
        $full = 'start /B "" ' . $cmd;
        @pclose(@popen($full, 'r'));
    } else {
        // Linux: nohup background
        $full = $cmd . ' > /dev/null 2>&1 &';
        @shell_exec($full);
    }

    return ['success' => true, 'message' => 'Backend RFID dicoba dijalankan', 'cmd' => $cmd];
}

// -----------------------------
// 5) Handle ACTION
// -----------------------------
if ($action === 'start') {
    $state['enabled']    = true;
    $state['updated_at'] = date('Y-m-d H:i:s');

    file_put_contents($ctrlPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $startInfo = start_python_backend($pythonPath, $scriptPath, $args);

    echo json_encode([
        'success' => true,
        'status'  => 'started',
        'enabled' => true,
        'reader'  => $reader,
        'backend' => $startInfo,
        'ctrl'    => $ctrlPath,
    ]);
    exit;
}

if ($action === 'stop') {
    $state['enabled']    = false;
    $state['updated_at'] = date('Y-m-d H:i:s');

    file_put_contents($ctrlPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'status'  => 'stopped',
        'enabled' => false,
        'reader'  => $reader,
        'ctrl'    => $ctrlPath,
    ]);
    exit;
}

// Default: status
echo json_encode([
    'success' => true,
    'status'  => 'status',
    'enabled' => (bool)($state['enabled'] ?? false),
    'reader'  => $reader,
    'ctrl'    => $ctrlPath,
]);
