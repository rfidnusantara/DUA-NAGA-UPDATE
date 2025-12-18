# -*- coding: utf-8 -*-
"""
run_rfid_multi.py

Satu script untuk 4 gudang / 4 reader (atau jalanin 1 per proses).
Kompatibel dengan pola Start/Stop via file kontrol JSON.

Cara pakai (paling stabil):
  python run_rfid_multi.py --reader zweena_reg
  python run_rfid_multi.py --reader dnk_gambiran_reg
  python run_rfid_multi.py --reader dnk_teblon_reg
  python run_rfid_multi.py --reader central_inout

Atau (opsional) 1 proses multi-thread:
  python run_rfid_multi.py --all

Web/PHP cukup mengubah file:
  static_files/<reader>/rfid_control.json  -> {"enabled": true/false}

Catatan:
- Setiap reader punya folder log sendiri, biar tidak saling overwrite.
- EPC terakhir ditulis ke: C:\\rfid\\<reader>.txt
"""

import os
import socket
import json
import csv
import select
import threading
import time
import argparse
from collections import deque
from datetime import datetime

# ============================================================
# CRC16 Hopeland (cocok dengan contoh CMD_STOP & inventory kamu)
#   poly = 0x8005, init = 0x0000, non-reflect, xorout = 0x0000
#   output disusun big-endian (HI LO)
# ============================================================
CRC16_POLY = 0x8005
CRC16_INIT = 0x0000

def crc16_hopeland(data: bytes) -> int:
    crc = CRC16_INIT
    for b in data:
        crc ^= (b << 8)
        for _ in range(8):
            if crc & 0x8000:
                crc = ((crc << 1) ^ CRC16_POLY) & 0xFFFF
            else:
                crc = (crc << 1) & 0xFFFF
    return crc & 0xFFFF

def build_cmd_from_body(body: bytes) -> str:
    """
    body = bytes perintah TANPA 'AA' dan TANPA CRC.
    return = string HEX ber-spasi: "AA <body...> <CRC_HI> <CRC_LO>"
    """
    crc = crc16_hopeland(body)
    crc_hi = (crc >> 8) & 0xFF
    crc_lo = crc & 0xFF
    full = bytes([0xAA]) + body + bytes([crc_hi, crc_lo])
    return " ".join(f"{x:02X}" for x in full)

def build_inventory_cmd(ant_mask: int) -> str:
    """
    Inventory continuous read EPC:
    body = 02 10 00 02 01 <ant_mask>
    ant_mask: ANT1=0x01, ANT2=0x02, ANT3=0x04, ANT4=0x08, ALL(1-4)=0x0F
    """
    body = bytes([0x02, 0x10, 0x00, 0x02, 0x01, ant_mask & 0xFF])
    return build_cmd_from_body(body)

# =======================
# PROFIL READER / GUDANG
# =======================
# NOTE:
# - epc_cmds kini SUDAH BENAR per antena (mask 01/02/04/08).
# - Ini memastikan worker round-robin benar-benar membaca ANT1..ANT4.
READER_PROFILES = {
    "zweena_reg": {
        "label": "CV. Zweena Adi Nugraha (Registrasi)",
        "ip": "172.16.36.4",
        "port": 8282,
        "ant_count": 4,
        "epc_cmds": {
            1: build_inventory_cmd(0x01),
            2: build_inventory_cmd(0x02),
            3: build_inventory_cmd(0x04),
            4: build_inventory_cmd(0x08),
        },
        "ant_status": {1: "Masuk", 2: "Masuk", 3: "Masuk", 4: "Masuk"},
    },

    "dnk_gambiran_reg": {
        "label": "PT. Dua Naga Kosmetindo - Gambiran (Registrasi)",
        "ip": "172.16.26.34",
        "port": 8686,
        "ant_count": 4,
        "epc_cmds": {
            1: build_inventory_cmd(0x01),
            2: build_inventory_cmd(0x02),
            3: build_inventory_cmd(0x04),
            4: build_inventory_cmd(0x08),
        },
        "ant_status": {1: "Masuk", 2: "Masuk", 3: "Masuk", 4: "Masuk"},
    },

    "dnk_teblon_reg": {
        "label": "PT. Dua Naga Kosmetindo - Teblon (Registrasi)",
        "ip": "172.16.33.189",
        "port": 8787,
        "ant_count": 4,
        "epc_cmds": {
            1: build_inventory_cmd(0x01),
            2: build_inventory_cmd(0x02),
            3: build_inventory_cmd(0x04),
            4: build_inventory_cmd(0x08),
        },
        "ant_status": {1: "Masuk", 2: "Masuk", 3: "Masuk", 4: "Masuk"},
    },

    "central_inout": {
        "label": "Gudang Central (In/Out - Multi Company)",
        "ip": "172.16.39.248",
        "port": 8383,
        "ant_count": 4,
        "epc_cmds": {
            1: build_inventory_cmd(0x01),
            2: build_inventory_cmd(0x02),
            3: build_inventory_cmd(0x04),
            4: build_inventory_cmd(0x08),
        },
        "ant_status": {1: "Masuk", 2: "Keluar", 3: "Masuk", 4: "Keluar"},
    },
}

# =======================
# KONFIG UMUM
# =======================
# Stop command kamu (tetap)
CMD_STOP = "AA 02 FF 00 00 A4 0F"

POLL_TIMEOUT_S   = 0.015
MAX_POLL_LOOPS   = 4
SEND_ROUNDS      = 1
BATCH_FLUSH_MS   = 250
BATCH_SIZE_LIMIT = 200
SO_RCVBUF        = 1 << 20

PRINT_EACH_EVENT = True
REPORT_INTERVAL_SEC = 1.0

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
STATIC_ROOT = os.path.join(BASE_DIR, "static_files")
RFID_WIN_DIR = r"C:\rfid"  # EPC terakhir per reader disimpan di sini (Windows)

def norm_hex(s: str) -> str:
    return (s or "").replace(" ", "").upper()

def ensure_dirs(path: str):
    os.makedirs(path, exist_ok=True)

def load_json(path: str, default):
    try:
        with open(path, "r", encoding="utf-8") as f:
            t = f.read().strip()
            return json.loads(t) if t else default
    except Exception:
        return default

def save_json(path: str, obj):
    ensure_dirs(os.path.dirname(path))
    with open(path, "w", encoding="utf-8") as f:
        json.dump(obj, f, ensure_ascii=False)
        f.flush()

def keterangan_for(ant_status: dict, ant: int) -> str:
    return ant_status.get(int(ant), "Unknown")

# =======================
# Reader Socket
# =======================
class ReaderClient:
    def __init__(self, ip, port):
        self.ip, self.port = ip, int(port)
        self.sock = None
        self.rx = bytearray()

    def connect(self):
        self.close()
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        s.setsockopt(socket.SOL_SOCKET, socket.SO_KEEPALIVE, 1)
        try:
            s.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, SO_RCVBUF)
        except Exception:
            pass
        s.settimeout(2.0)
        s.connect((self.ip, self.port))
        s.setblocking(False)
        self.sock = s
        self.send_hex(CMD_STOP)

    def close(self):
        if self.sock:
            try:
                self.sock.close()
            except Exception:
                pass
        self.sock = None

    def send_hex(self, cmd_hex):
        if not self.sock or not cmd_hex:
            return False
        try:
            self.sock.sendall(bytes.fromhex(norm_hex(cmd_hex)))
            return True
        except Exception:
            return False

    def poll_read(self):
        if not self.sock:
            return b""
        chunks = []
        for _ in range(MAX_POLL_LOOPS):
            r, _, _ = select.select([self.sock], [], [], POLL_TIMEOUT_S)
            if not r:
                break
            try:
                data = self.sock.recv(16384)
                if data:
                    chunks.append(data)
                else:
                    self.close()
                    break
            except (BlockingIOError, socket.timeout, OSError):
                break
        return b"".join(chunks)

# =======================
# Frame Parser HF340
# =======================
def parse_frames_into(rx: bytearray):
    """
    Parse frame balasan mulai header AA 12 ...
    Return list dict: {epc_b:bytes, antenna:int, rssi:int}
    """
    out = []
    i = 0
    while True:
        start = rx.find(b"\xAA\x12", i)
        if start < 0:
            if i > 0:
                del rx[:i]
            break
        if len(rx) - start < 5:
            if start > 0:
                del rx[:start]
            break

        length = int.from_bytes(rx[start+2:start+5], "big")
        end = start + 5 + length
        if len(rx) < end:
            if start > 0:
                del rx[:start]
            break

        frame = bytes(rx[start:end])
        i = end

        try:
            payload = frame[5:]
            epc_len = int.from_bytes(payload[0:2], "big")
            epc_b   = payload[2:2+epc_len]
            antenna = payload[4+epc_len]
            rssi    = payload[6+epc_len]
            out.append({"epc_b": bytes(epc_b), "antenna": int(antenna), "rssi": int(rssi)})
        except Exception:
            pass

    if i > 0:
        del rx[:i]
    return out

# =======================
# Stats sederhana
# =======================
class StatsPerSecond:
    def __init__(self):
        self.lock = threading.Lock()
        self.count_total = 0

    def add(self):
        with self.lock:
            self.count_total += 1

    def snap(self):
        with self.lock:
            c = self.count_total
            self.count_total = 0
            return c

class PerSecondReporter(threading.Thread):
    def __init__(self, label, stats):
        super().__init__(daemon=True)
        self.label = label
        self.stats = stats
        self.stop_event = threading.Event()

    def stop(self):
        self.stop_event.set()

    def run(self):
        while not self.stop_event.is_set():
            time.sleep(REPORT_INTERVAL_SEC)
            c = self.stats.snap()
            print(f"[{self.label}] [1s] {c} reads")

# =======================
# Async Logger per reader
# =======================
class AsyncLogger(threading.Thread):
    def __init__(self, label, queue, ant_status, paths, stats):
        super().__init__(daemon=True)
        self.label = label
        self.q = queue
        self.ant_status = ant_status
        self.paths = paths
        self.stats = stats

        self.latest = load_json(self.paths["latest"], {})

        self.stop_event = threading.Event()
        ensure_dirs(os.path.dirname(self.paths["csv"]))
        self.csv_f = open(self.paths["csv"], "a", newline="", encoding="utf-8")
        self.csv_w = csv.writer(self.csv_f)
        self.jsonl_f = open(self.paths["jsonl"], "a", encoding="utf-8")
        self.batch = []

        self.log_active = True  # OFF saat STOP

        if os.path.getsize(self.paths["csv"]) == 0:
            self.csv_w.writerow(["timestamp", "reader", "antenna", "epc", "code", "rssi_raw", "keterangan"])

    def stop(self):
        self.stop_event.set()

    def reset_jsonl_and_latest(self):
        try:
            self.jsonl_f.seek(0)
            self.jsonl_f.truncate()
            self.jsonl_f.flush()
            print(f"[{self.label}] [LOGGER] read_log.jsonl di-reset.")
        except Exception as e:
            print(f"[{self.label}] [LOGGER] Gagal reset jsonl:", e)

        try:
            self.latest = {}
            save_json(self.paths["latest"], self.latest)
            print(f"[{self.label}] [LOGGER] reads_latest.json di-reset.")
        except Exception as e:
            print(f"[{self.label}] [LOGGER] Gagal reset latest:", e)

        try:
            ensure_dirs(os.path.dirname(self.paths["result"]))
            with open(self.paths["result"], "w", encoding="utf-8") as f:
                f.write("")
        except Exception:
            pass

    def flush(self):
        if not self.batch:
            return
        if not self.log_active:
            self.batch.clear()
            return

        for ev in self.batch:
            ts   = ev["ts"]
            epc  = ev["epc"]
            ant  = ev["ant"]
            rssi = ev["rssi"]
            code = epc
            ket  = keterangan_for(self.ant_status, ant)

            self.csv_w.writerow([ts, self.label, ant, epc, code, rssi, ket])

            self.jsonl_f.write(json.dumps({
                "timestamp": ts,
                "reader": self.label,
                "antenna": ant,
                "epc": epc,
                "code": code,
                "rssi_raw": rssi,
                "keterangan": ket
            }, ensure_ascii=False) + "\n")

            self.latest[code] = {
                "epc": epc,
                "reader": self.label,
                "antenna": ant,
                "rssi_raw": rssi,
                "keterangan": ket,
                "timestamp": ts
            }

            if PRINT_EACH_EVENT:
                print(f"[{self.label}] [READ] ANT{ant} EPC={epc} RSSI={rssi} {ket}")

            try:
                ensure_dirs(os.path.dirname(self.paths["result"]))
                with open(self.paths["result"], "w", encoding="utf-8") as f:
                    f.write(epc)
            except Exception as e:
                print(f"[{self.label}] Gagal tulis result:", e)

        self.csv_f.flush()
        self.jsonl_f.flush()
        save_json(self.paths["latest"], self.latest)
        self.batch.clear()

    def run(self):
        last_flush = time.time()
        while not self.stop_event.is_set():
            if not self.log_active:
                while self.q:
                    try:
                        self.q.popleft()
                    except IndexError:
                        break
                self.batch.clear()
                time.sleep(0.05)
                continue

            while self.q:
                ev = self.q.popleft()
                epc_hex = ev["epc_b"].hex().upper()
                ant     = int(ev["antenna"])
                rssi    = int(ev["rssi"])

                ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                self.stats.add()
                self.batch.append({"ts": ts, "epc": epc_hex, "ant": ant, "rssi": rssi})
                if len(self.batch) >= BATCH_SIZE_LIMIT:
                    break

            now = time.time()
            if self.batch and (now - last_flush) * 1000 >= BATCH_FLUSH_MS:
                self.flush()
                last_flush = now

            time.sleep(0.003)

        self.flush()

# =======================
# Worker per reader
# =======================
class ReaderWorker(threading.Thread):
    def __init__(self, reader_key: str):
        super().__init__(daemon=False)
        if reader_key not in READER_PROFILES:
            raise ValueError(f"Reader '{reader_key}' tidak dikenal.")
        self.reader_key = reader_key
        self.cfg = READER_PROFILES[reader_key]
        self.stop_event = threading.Event()

    def stop(self):
        self.stop_event.set()

    def _paths(self):
        work = os.path.join(STATIC_ROOT, self.reader_key)
        ensure_dirs(work)
        ensure_dirs(RFID_WIN_DIR)

        return {
            "work": work,
            "trucks": os.path.join(work, "rfid_trucks.txt"),
            "csv": os.path.join(work, "read_log.csv"),
            "jsonl": os.path.join(work, "read_log.jsonl"),
            "latest": os.path.join(work, "reads_latest.json"),
            "ctrl": os.path.join(work, "rfid_control.json"),
            "result": os.path.join(RFID_WIN_DIR, f"{self.reader_key}.txt"),
        }

    def _ensure_files(self, paths):
        ensure_dirs(paths["work"])

        if not os.path.exists(paths["trucks"]):
            with open(paths["trucks"], "w", encoding="utf-8") as f:
                f.write(json.dumps({}, ensure_ascii=False))

        if not os.path.exists(paths["csv"]):
            with open(paths["csv"], "w", newline="", encoding="utf-8") as f:
                csv.writer(f).writerow(["timestamp", "reader", "antenna", "epc", "code", "rssi_raw", "keterangan"])

        if not os.path.exists(paths["jsonl"]):
            open(paths["jsonl"], "a", encoding="utf-8").close()

        if not os.path.exists(paths["latest"]):
            with open(paths["latest"], "w", encoding="utf-8") as f:
                f.write(json.dumps({}, ensure_ascii=False))

        if not os.path.exists(paths["result"]):
            with open(paths["result"], "w", encoding="utf-8") as f:
                f.write("")

        if not os.path.exists(paths["ctrl"]):
            save_json(paths["ctrl"], {"enabled": False})

    def run(self):
        paths = self._paths()
        self._ensure_files(paths)

        label = self.cfg["label"]
        ip = self.cfg["ip"]
        port = self.cfg["port"]
        ant_status = self.cfg.get("ant_status", {})
        ant_count = int(self.cfg.get("ant_count", 4))
        epc_cmds_cfg = self.cfg.get("epc_cmds", {})

        # build cmd cycle (ANT1..ANT4)
        cmd_cycle = []
        for ant in range(1, ant_count + 1):
            cmd = epc_cmds_cfg.get(ant)
            if cmd and str(cmd).strip():
                cmd_cycle.append((ant, cmd))

        if not cmd_cycle:
            print(f"[{label}] ERROR: epc_cmds kosong. Isi command EPC untuk antena 1..{ant_count}.")
            return

        print(f"\n=== START WORKER: {self.reader_key} ===")
        print(f"Label: {label}")
        print(f"IP/Port: {ip}:{port}")
        print(f"Control file: {paths['ctrl']}")
        print(f"Latest file:  {paths['latest']}")
        print(f"EPC result:   {paths['result']}")
        print("[INIT] Command cycle:")
        for a, c in cmd_cycle:
            print(f"   ANT{a} -> {c}")

        rc = ReaderClient(ip, port)

        q = deque()
        stats = StatsPerSecond()
        logger = AsyncLogger(label, q, ant_status, paths, stats)
        rep = PerSecondReporter(label, stats)
        logger.start()
        rep.start()

        last_enabled = None
        cycle_idx = 0

        try:
            while not self.stop_event.is_set():
                ctrl = load_json(paths["ctrl"], {"enabled": False})
                enabled = bool(ctrl.get("enabled", False))

                if enabled != last_enabled:
                    print(f"[{label}] [CTRL] enabled = {enabled}")
                    if not enabled:
                        logger.log_active = False
                        logger.reset_jsonl_and_latest()
                    else:
                        logger.log_active = True
                    last_enabled = enabled

                if not enabled:
                    if rc.sock:
                        rc.close()
                        print(f"[{label}] [CTRL] Reader stopped (socket closed).")
                    time.sleep(0.2)
                    continue

                if not rc.sock:
                    try:
                        rc.connect()
                        print(f"[{label}] [CTRL] Reader started (socket connected).")
                    except Exception:
                        time.sleep(0.3)
                        continue

                # send inventory round-robin ANT1..ANT4
                for _ in range(SEND_ROUNDS):
                    ant, cmd = cmd_cycle[cycle_idx % len(cmd_cycle)]
                    ok = rc.send_hex(cmd)
                    cycle_idx += 1
                    if not ok:
                        rc.close()
                        break

                data = rc.poll_read()
                if data:
                    rc.rx.extend(data)
                    events = parse_frames_into(rc.rx)
                    if events:
                        q.extend(events)

        except KeyboardInterrupt:
            print(f"[{label}] STOP oleh user (Ctrl+C).")
        finally:
            logger.stop()
            rep.stop()
            logger.join()
            rep.join()
            rc.close()

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--reader", choices=sorted(READER_PROFILES.keys()), help="Jalankan 1 reader tertentu")
    ap.add_argument("--all", action="store_true", help="Jalankan semua reader dalam 1 proses (multi-thread)")
    args = ap.parse_args()

    if not args.all and not args.reader:
        ap.error("wajib pilih --reader <nama> atau --all")

    if args.all:
        workers = [ReaderWorker(k) for k in READER_PROFILES.keys()]
        for w in workers:
            w.start()
        try:
            for w in workers:
                w.join()
        except KeyboardInterrupt:
            print("STOP (Ctrl+C).")
            for w in workers:
                w.stop()
    else:
        ReaderWorker(args.reader).run()

if __name__ == "__main__":
    main()
