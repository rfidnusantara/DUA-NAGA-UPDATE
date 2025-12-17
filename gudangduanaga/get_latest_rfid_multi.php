<?php
// get_latest_rfid.php (Multi Reader)
header('Content-Type: application/json');

$reader = $_GET['reader'] ?? 'central_inout';
$reader = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reader);
if ($reader === '') $reader = 'central_inout';

// File ini ditulis oleh backend Python.
// Rekomendasi: gunakan file per reader:
$baseDir   = __DIR__ . '/static_files';
$pathTry   = [
    $baseDir . '/' . $reader . '/reads_latest.json',
    $baseDir . '/reads_latest_' . $reader . '.json',
    $baseDir . '/reads_latest.json', // fallback (single reader lama)
];

$latestPath = null;
foreach ($pathTry as $p) {
    if (file_exists($p)) { $latestPath = $p; break; }
}

if (!$latestPath) {
    echo json_encode([
        'success' => false,
        'reader'  => $reader,
        'message' => 'File reads_latest.json belum ada untuk reader ini'
    ]);
    exit;
}

$json = file_get_contents($latestPath);
if ($json === false || trim($json) === '') {
    echo json_encode(['success' => false, 'reader' => $reader, 'message' => 'File kosong']);
    exit;
}

$data = json_decode($json, true);
if (!is_array($data) || empty($data)) {
    echo json_encode(['success' => false, 'reader' => $reader, 'message' => 'Data tidak valid']);
    exit;
}

// Ambil semua EPC unik dari file snapshot
$epcMap = []; // epc => true
foreach ($data as $code => $info) {
    if (!is_array($info)) continue;
    $epc = $info['epc'] ?? '';
    if ($epc === '') continue;
    $epcMap[$epc] = true;
}

$tags = array_keys($epcMap);
if (empty($tags)) {
    echo json_encode(['success' => false, 'reader' => $reader, 'message' => 'Tidak ada EPC di file']);
    exit;
}

sort($tags);

echo json_encode([
    'success' => true,
    'reader'  => $reader,
    'tags'    => $tags,
    'count'   => count($tags),
    'source'  => basename($latestPath),
]);
