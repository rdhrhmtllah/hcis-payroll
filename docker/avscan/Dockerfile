# Layanan pemindai antivirus untuk Library Konten (Cloud Run).
#
# Basisnya image ClamAV resmi karena ia sudah membawa clamd + freshclam beserta
# basis tanda tangan awalnya — membangunnya sendiri berarti mengurus pembaruan
# tanda tangan, yang justru bagian tersulitnya.
#
# BENTUK LAYANANNYA disengaja: ia menarik objeknya SENDIRI dari signed URL yang
# dikirim Laravel. Kalau Laravel yang mengunduh lalu meneruskan byte-nya ke sini,
# setiap berkas melintas dua kali dan Cloud Run aplikasi menahan 50 MB di memori
# hanya untuk meneruskannya.
FROM clamav/clamav:stable

USER root

# Basis image ClamAV pernah berpindah antara Alpine dan Debian antar rilis, dan
# menambatkan diri ke salah satunya berarti build ini pecah diam-diam saat tag
# 'stable' bergeser. Keduanya ditangani.
RUN set -eux; \
    if command -v apk >/dev/null 2>&1; then \
        apk add --no-cache python3 py3-pip curl; \
    else \
        apt-get update && apt-get install -y --no-install-recommends python3 python3-pip curl \
        && rm -rf /var/lib/apt/lists/*; \
    fi; \
    python3 -m pip install --break-system-packages --no-cache-dir \
        'flask>=3.0' 'requests>=2.31' 'gunicorn>=21.2' \
    || python3 -m pip install --no-cache-dir 'flask>=3.0' 'requests>=2.31' 'gunicorn>=21.2'

COPY server.py /srv/server.py
COPY start.sh /srv/start.sh
RUN chmod +x /srv/start.sh

# Cloud Run menyuntikkan PORT; 8080 hanya nilai default saat dijalankan lokal.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/srv/start.sh"]
