#!/usr/bin/env bash
# =========================================================================
#  migrate-server.sh -- pindah aplikasi dari VPS LAMA ke VPS BARU
#
#  Jalankan DI VPS LAMA. Butuh akses SSH ke VPS baru.
#
#     bash deploy/migrate-server.sh user@IP_VPS_BARU
#
#  Prasyarat di VPS BARU: sudah dijalankan  deploy/setup.sh  (stack + Apache +
#  config/local.php + DB kosong siap). Skrip ini hanya memindah DATA:
#     - dump database terbaru
#     - folder public/uploads
#  lalu menjalankan migrasi di sana. TIDAK menyentuh DNS (lakukan manual
#  setelah verifikasi).
# =========================================================================
set -euo pipefail

REMOTE="${1:-}"
[ -z "${REMOTE}" ] && { echo "Pemakaian: bash deploy/migrate-server.sh user@IP_VPS_BARU"; exit 1; }

APP_DIR="${APP_DIR:-/var/www/stok}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/var/www/stok}"
cd "${APP_DIR}"

read -r DB_NAME DB_USER DB_PASS DB_HOST < <(php -r '
  $c = require "config/local.php";
  echo ($c["db_name"] ?? "db_stok_proyek") . " " . ($c["db_user"] ?? "root") . " " .
       ($c["db_pass"] ?? "") . " " . ($c["db_host"] ?? "localhost");
')

TMP="/tmp/stok-move-$(date +%s)"
mkdir -p "${TMP}"

echo "==> 1/4  Dump DB dari VPS lama"
MYSQL_PWD="${DB_PASS}" mysqldump --host="${DB_HOST}" --user="${DB_USER}" \
  --single-transaction --quick --routines --triggers "${DB_NAME}" > "${TMP}/db.sql"

echo "==> 2/4  Arsip folder uploads"
tar czf "${TMP}/uploads.tgz" -C "${APP_DIR}" public/uploads

echo "==> 3/4  Kirim ke VPS baru (${REMOTE})"
scp "${TMP}/db.sql" "${TMP}/uploads.tgz" "${REMOTE}:/tmp/"

echo "==> 4/4  Restore di VPS baru + migrasi"
ssh "${REMOTE}" bash -s <<REMOTE_SCRIPT
set -euo pipefail
cd "${REMOTE_APP_DIR}"
read -r RN RU RP RH < <(php -r '\$c=require "config/local.php"; echo (\$c["db_name"]??"db_stok_proyek")." ".(\$c["db_user"]??"root")." ".(\$c["db_pass"]??"")." ".(\$c["db_host"]??"localhost");')
MYSQL_PWD="\${RP}" mysql --host="\${RH}" --user="\${RU}" "\${RN}" < /tmp/db.sql
tar xzf /tmp/uploads.tgz -C "${REMOTE_APP_DIR}"
sudo -n chown -R www-data:www-data public/uploads storage logs 2>/dev/null \
  || echo "   ! Jalankan manual di VPS baru: sudo chown -R www-data:www-data ${REMOTE_APP_DIR}/{public/uploads,storage,logs}"
php bin/migrate.php
rm -f /tmp/db.sql /tmp/uploads.tgz
echo "   OK. Versi: \$(git rev-parse --short HEAD)"
REMOTE_SCRIPT

rm -rf "${TMP}"

cat <<DONE

============================================================
 DATA sudah pindah ke ${REMOTE}.

 Langkah manual berikutnya:
   1) Di VPS baru: pasang SSL untuk subdomain FINAL:
        sudo certbot --apache -d hexastok.hexamultienergi.com
   2) Verifikasi aplikasi di VPS baru (akses lewat IP / entri /etc/hosts).
   3) Ubah A record subdomain -> IP VPS baru (TTL sudah 300 dtk sebelumnya).
   4) Setelah yakin: matikan VPS lama, naikkan TTL DNS ke 3600.
============================================================
DONE
