#!/usr/bin/env bash
# =========================================================================
#  cleanup.sh -- buang file lama yang menumpuk di disk (cron mingguan)
#
#  Target:
#    - storage/backups/*.sql   backup DB manual dari menu Pengaturan Sistem
#                              (aplikasi tidak pernah menghapusnya sendiri)
#    - logs/error.log.*        arsip rotasi log lama
#    - <temp>/dompdf_*         sisa file sementara Dompdf yang nyangkut
#
#  Export PDF/Excel di modul Laporan di-stream langsung ke browser (tidak
#  ditulis ke disk), jadi tidak ada yang perlu dibersihkan di sana.
#
#  Pakai lewat cron (mingguan), contoh baris crontab user:
#     30 3 * * 0  /var/www/stok/deploy/cleanup.sh >> $HOME/stok-cleanup.log 2>&1
#
#  Setelan lewat env var (opsional):
#     APP_DIR   folder aplikasi           (default: /var/www/stok)
#     DAYS      umur file "lama" (hari)   (default: 30)
#     KEEP      backup DB terbaru disisakan (default: 7)
#     DRY_RUN   1 = laporkan saja, jangan hapus
#
#  Uji dulu tanpa menghapus apa pun:
#     DRY_RUN=1 bash /var/www/stok/deploy/cleanup.sh
# =========================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stok}"
DAYS="${DAYS:-30}"
KEEP="${KEEP:-7}"

cd "${APP_DIR}"

ARGS=(--days="${DAYS}" --keep="${KEEP}")
[ "${DRY_RUN:-0}" = "1" ] && ARGS+=(--dry-run)

php bin/cleanup.php "${ARGS[@]}"
