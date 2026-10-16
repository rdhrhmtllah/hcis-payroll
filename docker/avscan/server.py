"""Pembungkus HTTP tipis di atas clamd untuk Library Konten.

Kontraknya sengaja sempit — satu endpoint, satu keputusan:

    POST /scan  {"url": "<signed GET URL>", "maks_byte": 52428800}
      -> 200 {"bersih": true,  "tanda_tangan": null}
      -> 200 {"bersih": false, "tanda_tangan": "Eicar-Test-Signature"}

    GET /health -> 200 bila clamd menjawab PING

Objeknya ditarik LAYANAN INI, bukan diteruskan Laravel. Bedanya nyata: byte-nya
melintas sekali (GCS -> pemindai) alih-alih dua kali (GCS -> Laravel -> pemindai),
dan Cloud Run aplikasi tak perlu menahan berkas 50 MB di memori sebagai perantara.

Autentikasi TIDAK ditangani di sini. Layanan ini dijalankan tanpa akses publik
dan hanya menerima pemanggil ber-token OIDC yang divalidasi Cloud Run di depan
kontainer — memasang token bersama di sini justru menambah rahasia yang harus
dirawat tanpa menambah jaminan apa pun.
"""

import os
import socket

import requests
from flask import Flask, jsonify, request

app = Flask(__name__)

SOCKETS = ("/tmp/clamd.sock", "/run/clamav/clamd.sock", "/var/run/clamav/clamd.sock")
CHUNK = 64 * 1024
BATAS_DEFAULT = 50 * 1024 * 1024


def _sambung():
    """Soket clamd; jalurnya berbeda antar versi image, jadi dicoba berurutan."""
    for jalur in SOCKETS:
        if os.path.exists(jalur):
            s = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
            s.settimeout(300)
            s.connect(jalur)
            return s
    raise RuntimeError("soket clamd tidak ditemukan")


@app.get("/health")
def health():
    try:
        s = _sambung()
        s.sendall(b"zPING\0")
        jawab = s.recv(64)
        s.close()
        # Perintah ber-awalan "z" membuat clamd membalas dengan penutup NUL.
        # bytes.strip() hanya membuang spasi, bukan b"\0" — melewatkan ini
        # membuat balasan "PONG\0" yang sehat terbaca sebagai layanan mati.
        bersih = jawab.strip().strip(b"\0")
        return jsonify(ok=bersih == b"PONG"), 200 if bersih == b"PONG" else 503
    except Exception as e:  # noqa: BLE001
        return jsonify(ok=False, error=str(e)), 503


@app.post("/scan")
def scan():
    data = request.get_json(silent=True) or {}
    url = data.get("url")
    batas = int(data.get("maks_byte") or BATAS_DEFAULT)

    if not url:
        return jsonify(error="url wajib diisi"), 400

    try:
        s = _sambung()
    except Exception as e:  # noqa: BLE001
        # 503, bukan 200-dengan-bersih=true. Pemindai yang tak bisa memindai
        # HARUS terlihat gagal; memalsukan "bersih" saat clamd mati adalah cara
        # paling halus untuk mematikan seluruh lapisan ini tanpa ada yang sadar.
        return jsonify(error=f"clamd tidak siap: {e}"), 503

    try:
        s.sendall(b"zINSTREAM\0")

        with requests.get(url, stream=True, timeout=120) as r:
            if r.status_code != 200:
                return jsonify(error=f"objek tak terambil: HTTP {r.status_code}"), 502

            total = 0
            for blok in r.iter_content(CHUNK):
                if not blok:
                    continue
                total += len(blok)
                if total > batas:
                    # Tutup stream dengan potongan berukuran nol sesuai protokol
                    # INSTREAM sebelum menyerah, supaya clamd tidak menggantung.
                    s.sendall(b"\0\0\0\0")
                    return jsonify(error="objek melebihi batas pemindaian"), 413
                s.sendall(len(blok).to_bytes(4, "big") + blok)

        s.sendall(b"\0\0\0\0")

        jawab = b""
        while True:
            potongan = s.recv(4096)
            if not potongan:
                break
            jawab += potongan
            if b"\0" in potongan:
                break

        teks = jawab.decode("utf-8", "replace").strip().strip("\0")

        if teks.endswith("OK") and "FOUND" not in teks:
            return jsonify(bersih=True, tanda_tangan=None, byte=total), 200

        if "FOUND" in teks:
            # Format clamd: "stream: Eicar-Test-Signature FOUND"
            tanda = teks.split(":", 1)[-1].replace("FOUND", "").strip()
            return jsonify(bersih=False, tanda_tangan=tanda or "tidak diketahui", byte=total), 200

        return jsonify(error=f"jawaban clamd tak dikenali: {teks}"), 502
    except Exception as e:  # noqa: BLE001
        return jsonify(error=str(e)), 503
    finally:
        try:
            s.close()
        except Exception:  # noqa: BLE001
            pass
