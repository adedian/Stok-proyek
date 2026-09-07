#!/usr/bin/env bash
# =========================================================================
#  update.sh -- tarik perubahan terbaru dari git & terapkan di VPS
#
#  Jalankan tiap kali ada commit baru di master:
#     bash /var/www/stok/deploy/update.sh
#
#  Yang dilakukan:
#     git pull --ff-only  ->  composer install  ->  migrasi DB  ->  fix izin
#  Aman diulang. Kalau git pull gagal (ada perubahan lokal di server),
#  skrip berhenti tanpa merusak apa pun.
# =========================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stok}"
cd "${APP_DIR}"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

say "Repo: $(git -C "${APP_DIR}" rev-parse --short HEAD) -> tarik terbaru"
git fetch --prune origin
git pull --ff-only origin master

say "Composer (production)"
composer install --no-dev --optimize-autoloader --no-interaction

say "Migrasi database"
php bin/migrate.php --status || true
php bin/migrate.php

say "Regen ikon PWA (kalau logo berubah)"
php bin/make_pwa_icons.php || true

say "Izin folder tulis"
sudo -n chown -R www-data:www-data public/uploads storage logs 2>/dev/null \
  || echo "  ! (lewati) chown butuh sudo -- jalankan manual bila upload bermasalah:" \
          "sudo chown -R www-data:www-data ${APP_DIR}/{public/uploads,storage,logs}"
sudo -n find public/uploads storage logs -type d -exec chmod 775 {} \; 2>/dev/null || true

# OPcache: reload PHP-FPM/Apache supaya file PHP baru kebaca (bukan cache lama).
if systemctl is-active --quiet apache2; then
  sudo systemctl reload apache2
fi

say "Selesai. Versi sekarang: $(git rev-parse --short HEAD) \"$(git log -1 --pretty=%s)\""
