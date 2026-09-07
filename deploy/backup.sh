#!/usr/bin/env bash
# =========================================================================
#  backup.sh -- backup database + folder upload ke luar folder aplikasi
#
#  Pakai lewat cron (harian), contoh crontab user:
#     15 2 * * *  /var/www/stok/deploy/backup.sh >> $HOME/stok-backup.log 2>&1
#
#  Hasil: /var/backups/stok/YYYY-mm-dd_HHMM/{db.sql.gz, uploads.tgz}
#  Menyimpan 14 backup terakhir, sisanya dihapus.
#
#  Kredensial DB dibaca dari config/local.php (tidak perlu ditulis ulang).
#  DISARANKAN: setelah ini, sinkronkan /var/backups/stok ke penyimpanan lain
#  (object storage / Google Drive via rclone) -- backup di VPS yang sama
#  bukan backup kalau VPS-nya hilang.
# =========================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stok}"
DEST_ROOT="${DEST_ROOT:-/var/backups/stok}"
KEEP="${KEEP:-14}"

cd "${APP_DIR}"

# Ambil kredensial dari config/local.php lewat PHP (sumber kebenaran tunggal).
read -r DB_NAME DB_USER DB_PASS DB_HOST < <(php -r '
  $c = require "config/local.php";
  echo ($c["db_name"] ?? "db_stok_proyek") . " " .
       ($c["db_user"] ?? "root") . " " .
       ($c["db_pass"] ?? "") . " " .
       ($c["db_host"] ?? "localhost");
')

STAMP="$(date +%Y-%m-%d_%H%M)"
DEST="${DEST_ROOT}/${STAMP}"
mkdir -p "${DEST}"

echo "[$(date '+%F %T')] backup -> ${DEST}"

MYSQL_PWD="${DB_PASS}" mysqldump \
  --host="${DB_HOST}" --user="${DB_USER}" \
  --single-transaction --quick --routines --triggers \
  "${DB_NAME}" | gzip -9 > "${DEST}/db.sql.gz"

tar czf "${DEST}/uploads.tgz" -C "${APP_DIR}" public/uploads

# Rotasi: sisakan $KEEP terbaru
ls -1dt "${DEST_ROOT}"/*/ 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -rf

echo "[$(date '+%F %T')] selesai ($(du -sh "${DEST}" | cut -f1))"
