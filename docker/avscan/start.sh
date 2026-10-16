#!/bin/sh
set -e

# freshclam dijalankan di latar belakang, BUKAN sebagai syarat siap.
# Image ClamAV sudah membawa basis tanda tangan; menunggu pembaruan selesai
# sebelum melayani berarti cold start bisa memakan menit, dan Cloud Run keburu
# menganggap kontainernya gagal start.
freshclam --daemon --checks=4 >/dev/null 2>&1 &

# clamd memuat ~1 GB basis tanda tangan ke memori — inilah alasan layanan ini
# butuh 2 Gi dan min-instances=1. Tanpa instance yang selalu hidup, setiap
# pemindaian pertama membayar ulang ~15 detik pemuatan.
clamd &

# Tunggu soket clamd siap sebelum menerima permintaan, kalau tidak permintaan
# pertama akan gagal dengan "connection refused" yang menyesatkan.
i=0
while [ ! -S /tmp/clamd.sock ] && [ ! -S /run/clamav/clamd.sock ] && [ "$i" -lt 120 ]; do
    i=$((i + 1))
    sleep 1
done

exec gunicorn --bind "0.0.0.0:${PORT:-8080}" --workers 1 --threads 4 \
    --timeout 300 --chdir /srv server:app
