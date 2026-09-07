#!/usr/bin/env bash
# =========================================================================
#  setup.sh -- provisioning + deploy pertama kali di VPS (Ubuntu 22.04/24.04)
#
#  Jalankan SEKALI di VPS baru, sebagai user biasa yang punya sudo:
#     bash deploy/setup.sh
#
#  Idempoten secukupnya (aman diulang). Yang ditanyakan interaktif:
#     - domain/subdomain
#     - password database aplikasi
#     - lokasi file dump database (opsional, boleh dikosongkan)
#
#  Setelah selesai: arahkan A record subdomain ke IP VPS lalu jalankan
#     sudo certbot --apache -d <subdomain>
# =========================================================================
set -euo pipefail

APP_DIR="/var/www/stok"
REPO_URL="https://github.com/adedian/Stok-proyek.git"
DB_NAME="db_stok_proyek"
DB_USER="stok"
PHP_MIN="8.1"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!  %s\033[0m\n' "$*"; }

[ "$(id -u)" -eq 0 ] && { echo "Jangan jalankan sebagai root. Pakai user biasa + sudo."; exit 1; }

read -rp "Subdomain (mis. stok-test.hexamultienergi.com): " DOMAIN
[ -z "${DOMAIN}" ] && { echo "Domain wajib diisi."; exit 1; }
read -rsp "Password untuk user DB '${DB_USER}': " DB_PASS; echo
[ -z "${DB_PASS}" ] && { echo "Password DB wajib diisi."; exit 1; }
read -rp "Path file dump .sql untuk di-restore (Enter = lewati): " DUMP_FILE

say "1/7  Update sistem & pasang paket"
sudo apt-get update -y
sudo apt-get upgrade -y
sudo apt-get install -y \
  apache2 mariadb-server \
  php php-cli php-mysql php-mbstring php-xml php-zip php-gd php-curl php-intl \
  git unzip curl ca-certificates

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [ "$(printf '%s\n%s\n' "$PHP_MIN" "$PHP_VER" | sort -V | head -1)" != "$PHP_MIN" ]; then
  warn "PHP ${PHP_VER} terpasang, aplikasi butuh >= ${PHP_MIN}. Lanjut tapi cek lagi."
fi

say "2/7  Aktifkan modul Apache"
sudo a2enmod rewrite headers ssl mime >/dev/null
sudo systemctl enable --now apache2 mariadb >/dev/null

say "3/7  Composer"
if ! command -v composer >/dev/null; then
  EXPECTED="$(curl -sS https://composer.github.io/installer.sig)"
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  [ "$EXPECTED" = "$ACTUAL" ] || { echo "Checksum installer Composer tidak cocok."; exit 1; }
  sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

say "4/7  Database + user aplikasi"
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

say "5/7  Ambil kode aplikasi ke ${APP_DIR}"
if [ ! -d "${APP_DIR}/.git" ]; then
  sudo mkdir -p "${APP_DIR}"
  sudo chown -R "$USER":www-data "${APP_DIR}"
  git clone "${REPO_URL}" "${APP_DIR}"
else
  warn "${APP_DIR} sudah berisi repo, git pull saja."
  git -C "${APP_DIR}" pull --ff-only
fi
cd "${APP_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction

say "6/7  Konfigurasi per-server (config/local.php) + restore DB"
if [ ! -f config/local.php ]; then
  cat > config/local.php <<PHP
<?php
return [
    'app_env'        => 'production',
    'db_host'        => 'localhost',
    'db_name'        => '${DB_NAME}',
    'db_user'        => '${DB_USER}',
    'db_pass'        => '${DB_PASS}',
    'db_charset'     => 'utf8mb4',
    'mysqldump_path' => 'mysqldump',
];
PHP
  echo "  config/local.php dibuat."
else
  warn "config/local.php sudah ada, tidak ditimpa."
fi

if [ -n "${DUMP_FILE}" ] && [ -f "${DUMP_FILE}" ]; then
  echo "  restore ${DUMP_FILE} ..."
  MYSQL_PWD="${DB_PASS}" mysql -u "${DB_USER}" "${DB_NAME}" < "${DUMP_FILE}"
  php bin/migrate.php --status || true
  php bin/migrate.php || true
else
  warn "Tidak ada dump. Import DB manual lalu jalankan: php bin/migrate.php"
fi

# regen ikon PWA (kalau logo pernah diganti) -- aman kalau gagal
php bin/make_pwa_icons.php || true

say "7/7  Izin folder tulis + VirtualHost"
sudo chown -R www-data:www-data public/uploads storage logs
sudo find public/uploads storage logs -type d -exec chmod 775 {} \;
sudo find public/uploads storage logs -type f -exec chmod 664 {} \; 2>/dev/null || true

VHOST="/etc/apache2/sites-available/stok.conf"
sudo tee "${VHOST}" >/dev/null <<CONF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/stok_error.log
    CustomLog \${APACHE_LOG_DIR}/stok_access.log combined
</VirtualHost>
CONF

sudo a2ensite stok.conf >/dev/null
sudo a2dissite 000-default.conf >/dev/null 2>&1 || true
sudo apache2ctl configtest
sudo systemctl reload apache2

cat <<DONE

============================================================
 SELESAI (HTTP).  Langkah terakhir:

 1) Arahkan A record  ${DOMAIN}  ->  IP VPS ini.
 2) Setelah DNS propagasi:
       sudo apt-get install -y certbot python3-certbot-apache
       sudo certbot --apache -d ${DOMAIN}
 3) Rotasi akun default super admin (mencetak password acak baru sekali):
       php ${APP_DIR}/bin/reset_user_password.php admin --yes
    (atau nonaktifkan akun 'admin' lewat User Management, pakai 'ade')

 Update berikutnya cukup:  bash ${APP_DIR}/deploy/update.sh
============================================================
DONE
