#!/bin/sh
set -e

# Secara paksa mengganti 'Listen 80' dengan port yang diberikan oleh Cloud Run.
# Jika variabel $PORT tidak ada, gunakan 8080 sebagai default.
sed -i "s/Listen 80/Listen ${PORT:-8080}/g" /etc/apache2/ports.conf

# Menjalankan perintah utama (CMD) dari Dockerfile, yaitu "apache2-foreground".
exec "$@"
