<?php
// rfid_config.php
// Konfigurasi mapping reader Python <-> file JSON untuk diakses PHP.

// NOTE: sesuaikan path ini dengan lokasi folder rfid_outbox yang dibuat Python.
// Jika run_rfid_multi.py dan rfid_outbox ada di root project yang sama:
$RFID_OUTBOX_DIR = __DIR__ . '/rfid_outbox';

// Jika rfid_outbox ada di folder lain (misal /python/rfid_outbox), ganti:
// $RFID_OUTBOX_DIR = __DIR__ . '/../python/rfid_outbox';

// Daftar reader yang DISEPAKATI dengan run_rfid_multi.py (reader_key)
$RFID_READERS = [
    'zweena_reg' => [
        'label'      => 'Registrasi CV. Zweena Adi Nugraha',
        'warehouse'  => 'CV. Zweena Adi Nugraha',
        'last_file'  => $RFID_OUTBOX_DIR . '/last_zweena_reg.json',
        'ctrl_file'  => $RFID_OUTBOX_DIR . '/ctrl_zweena_reg.json',
    ],
    'dnk_gambiran_reg' => [
        'label'      => 'Registrasi PT. Dua Naga Kosmetindo - Gambiran',
        'warehouse'  => 'PT. Dua Naga Kosmetindo - Gambiran',
        'last_file'  => $RFID_OUTBOX_DIR . '/last_dnk_gambiran_reg.json',
        'ctrl_file'  => $RFID_OUTBOX_DIR . '/ctrl_dnk_gambiran_reg.json',
    ],
    'dnk_teblon_reg' => [
        'label'      => 'Registrasi PT. Dua Naga Kosmetindo - Teblon',
        'warehouse'  => 'PT. Dua Naga Kosmetindo - Teblon',
        'last_file'  => $RFID_OUTBOX_DIR . '/last_dnk_teblon_reg.json',
        'ctrl_file'  => $RFID_OUTBOX_DIR . '/ctrl_dnk_teblon_reg.json',
    ],
    'central_inout' => [
        'label'      => 'Gudang Central IN/OUT',
        'warehouse'  => 'Gudang Central',
        'last_file'  => $RFID_OUTBOX_DIR . '/last_central_inout.json',
        'ctrl_file'  => $RFID_OUTBOX_DIR . '/ctrl_central_inout.json',
    ],
];
