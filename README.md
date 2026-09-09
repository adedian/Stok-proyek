# Sistem Kontrol Stok Proyek — PT. Hexa Multi Energi

Aplikasi web internal untuk kontrol stok proyek: Purchase Order, Penerimaan &
Pengeluaran Barang, Stok Opname, Kas, Invoice Keluar / Surat Jalan / Tanda Terima,
Laporan, dan Master Data. Bisa di-*install* sebagai aplikasi di HP (PWA).

- **Stack**: PHP 8.1+ (tanpa framework, MVC sendiri) · MySQL / MariaDB · Apache
  (`.htaccess` + `mod_rewrite`) · Bootstrap 5 · Composer (Dompdf untuk PDF,
  PhpSpreadsheet untuk Excel) · Ghostscript opsional (kompres PDF upload).
- **Repo**: `https://github.com/adedian/Stok-proyek` (publik).

## Rencana hosting (2 fase)

| Fase | Di mana | Kapan |
|---|---|---|
| **FASE 1 — sekarang** | Subdomain `stok.hexamultienergi.com` di **hosting cPanel** yang sudah ada (kode + database + folder upload semua di sana) | langsung, murah, cukup untuk mulai |
| **FASE 2 — nanti** | **VPS Hostinger** (khusus aplikasi ini) | kalau kuota disk cPanel mulai sesak di tengah jalan (lihat [Bagian 4](#4-kapasitas-penyimpanan--kapan-pindah-ke-vps)) |

Kode & alur kerja **tidak berubah** antar fase — pindah = salin data + ganti DNS.
Skrip di folder [`deploy/`](deploy/) khusus untuk FASE 2 (VPS), **tidak dipakai di
cPanel**.

---

## Daftar isi

- [1. Menjalankan di komputer lokal (XAMPP)](#1-menjalankan-di-komputer-lokal-xampp)
- [2. Cara kerja deploy (baca dulu)](#2-cara-kerja-deploy-baca-dulu)
- [3. FASE 1 — Deploy ke subdomain hosting (cPanel)](#3-fase-1--deploy-ke-subdomain-hosting-cpanel)
  - [A. Persiapan di laptop](#a--persiapan-di-laptop--10-menit)
  - [B. Siapkan subdomain, database & PHP di cPanel](#b--siapkan-subdomain-database--php-di-cpanel--15-menit)
  - [C. Pasang aplikasi lewat Terminal cPanel](#c--pasang-aplikasi-lewat-terminal-cpanel--1520-menit)
  - [D. HTTPS, akun, & pengujian](#d--https-akun--pengujian--10-menit)
- [4. Kapasitas penyimpanan & kapan pindah ke VPS](#4-kapasitas-penyimpanan--kapan-pindah-ke-vps)
- [5. Kerja sehari-hari (update aplikasi)](#5-kerja-sehari-hari-update-aplikasi)
- [6. Backup](#6-backup)
- [7. FASE 2 — Pindah ke VPS Hostinger](#7-fase-2--pindah-ke-vps-hostinger)
- [8. Peta folder & perintah penting](#8-peta-folder--perintah-penting)
- [9. Troubleshooting](#9-troubleshooting)

---

## 1. Menjalankan di komputer lokal (XAMPP)

Sudah jalan di komputer ini. Ringkasnya untuk komputer lain:

1. Install **XAMPP** (PHP 8.1+), start **Apache** & **MySQL**.
2. Taruh folder project di `C:\xampp\htdocs\stok-proyek`.
3. Buat database `db_stok_proyek`, import `database/schema.sql` lalu jalankan
   migrasi: `C:\xampp\php\php.exe bin\migrate.php`.
   (atau import dump lengkap kalau punya).
4. Salin `config/local.example.php` → `config/local.php`, set
   `'app_env' => 'development'` + kredensial DB lokal.
5. `composer install` (butuh Composer) supaya Export PDF/Excel jalan.
6. Buka `http://localhost/stok-proyek/public/`.

Login default: `admin` / `admin123` (ganti setelah deploy).

---

## 2. Cara kerja deploy (baca dulu)

Aplikasi ini isinya cuma **3 bagian**:

| Bagian | Disimpan di | Cara pindah / backup |
|---|---|---|
| **Kode program** | GitHub (repo publik) | `git clone` / `git pull` |
| **Konfigurasi server** | 1 file `config/local.php` (tidak masuk git) | dibuat manual sekali per server |
| **Data** | Database MySQL + folder `public/uploads/` | 1 file `.sql` + folder `uploads/` |

Konsekuensinya:

- **Kamu tetap ngoding di laptop** (XAMPP + Claude Code). Hosting cuma *menerima*
  hasil: `git push` di laptop → `git pull` di hosting.
- **Aplikasi di hosting JANGAN diedit langsung.** Semua perubahan lewat git.
- **Pindah server** (cPanel → VPS, atau VPS → VPS) = pasang ulang + salin data +
  ganti DNS.

Alur besar:

```
┌─────────────┐   git push    ┌──────────┐   git pull            ┌──────────────┐
│  LAPTOP     │ ────────────▶ │  GitHub  │ ────────────────────▶ │  HOSTING     │
│  XAMPP +    │               │  master  │                       │  cPanel      │
│  Claude Code│ ◀──────────── │          │                       │  (lalu VPS)  │
└─────────────┘   git pull    └──────────┘                       └──────────────┘
```

Istilah singkat:

- **cPanel** = panel kontrol hosting sewaan (subdomain, database, file, cron).
- **Terminal** (di cPanel, bagian *Advanced*) = baris perintah di dalam akun hosting.
- **Document Root** = folder yang "ditunjuk" oleh sebuah domain/subdomain. Untuk
  aplikasi ini harus diarahkan ke folder **`public/`** (bukan folder atasnya),
  supaya `config/`, `app/`, `logs/` tidak bisa dibuka dari web.
- **DNS / A record** = "buku alamat" yang menghubungkan `stok.hexamultienergi.com`
  ke server. Kalau subdomain dibuat langsung di cPanel domain utama, DNS-nya
  otomatis.
- **AutoSSL** = HTTPS gratis yang cPanel pasang sendiri untuk tiap subdomain.

Skrip di folder [`deploy/`](deploy/) (`setup.sh`, `update.sh`, `backup.sh`,
`cleanup.sh`, `migrate-server.sh`) **khusus VPS Ubuntu** — dipakai di
[FASE 2](#7-fase-2--pindah-ke-vps-hostinger), bukan di cPanel.

---

## 3. FASE 1 — Deploy ke subdomain hosting (cPanel)

Target: `https://stok.hexamultienergi.com`, kode di `~/stok-proyek`, Document Root
subdomain menunjuk ke `~/stok-proyek/public`.

Total waktu pertama kali: **± 45–60 menit** (paling lama nunggu AutoSSL & Composer).

> **Catatan PHP CLI di Terminal cPanel.** `php` di Terminal kadang masih versi lama.
> Cek dulu `php -v`:
> - kalau sudah **8.1+** → pakai `php` apa adanya di semua perintah di bawah;
> - kalau bukan → pakai path lengkap versi yang kamu pilih di **MultiPHP Manager**,
>   contoh **`/opt/cpanel/ea-php82/root/usr/bin/php`** (ganti `ea-php82` sesuai versimu).
>
> Di panduan ini binari itu ditulis **`PHP`**. Ganti dengan salah satu di atas.

### A — Persiapan di laptop · ~10 menit

#### A1. Pastikan kode terbaru sudah di GitHub

```powershell
cd C:\xampp\htdocs\stok-proyek
git status
git push origin master
```

`git status` harus `working tree clean`; `git push` membalas `Everything up-to-date`
atau menampilkan hash commit terkirim.

#### A2. Buat dump (salinan) database lokal

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --databases db_stok_proyek --add-drop-database --result-file=C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql
```

- Perintah ini **tidak menampilkan output** kalau berhasil.
- Cek: file `db_stok_proyek.sql` muncul, ukuran > 100 KB.
- File ini berisi **skema + data + riwayat migrasi**. Sudah otomatis di-*ignore*
  git, jadi tidak akan ke-commit.

> **Mau produksi mulai bersih tanpa data uji?** Lewati langkah ini. Nanti di
> [C4](#c4-import-database) import `database/schema.sql` lalu jalankan
> `PHP bin/migrate.php --baseline`.

### B — Siapkan subdomain, database & PHP di cPanel · ~15 menit

#### B1. Buat subdomain

cPanel → **Domains** (atau **Subdomains** di cPanel lama) → **Create A New Domain** /
**Create**:

| Kolom | Isi |
|---|---|
| Domain | `stok.hexamultienergi.com` |
| Document Root | **`/home/USER_KAMU/stok-proyek/public`** |

- Ganti `USER_KAMU` dengan nama user cPanel (lihat pojok kanan cPanel, atau
  jalankan `whoami` di Terminal).
- Kalau cPanel **memaksa** Document Root harus di dalam `public_html/`, isi apa saja
  dulu (mis. `public_html/stok`), nanti diganti symlink di [C1](#c1-ambil-kode).
- DNS otomatis dibuat cPanel karena ini subdomain dari domain utama akun.

#### B2. Set versi PHP + ekstensi

1. cPanel → **MultiPHP Manager** → centang `stok.hexamultienergi.com` → set
   **PHP 8.1** (atau 8.2 / 8.3) → **Apply**.
2. cPanel → **Select PHP Version** (atau **MultiPHP INI Editor**) → tab
   **Extensions** → pastikan **aktif**:
   `gd`, `mbstring`, `dom` / `xml`, `zip`, `curl`, `intl`, `pdo_mysql`,
   `mysqlnd`, `fileinfo`, `exif`, `openssl`.
3. Tab **Options** (INI Editor) — kalau ada, naikkan sedikit biar upload lega:
   `upload_max_filesize = 30M`, `post_max_size = 32M`, `memory_limit = 256M`.

> **`disable_functions`.** Banyak hosting mematikan `proc_open` / `exec`.
> Dampaknya di aplikasi ini:
> - **Kompres PDF upload** otomatis mati → PDF tetap tersimpan, hanya tidak
>   dikecilkan (aman, tidak error).
> - Menu **Pengaturan Sistem → Backup Database** bisa gagal → pakai
>   **phpMyAdmin → Export** atau backup bawaan cPanel (lihat [Bagian 6](#6-backup)).

#### B3. Buat database + user MySQL

cPanel → **MySQL Databases**:

1. **Create New Database**: `stokproyek` → jadi `USER_stokproyek`.
2. **Add New User**: `stok` → jadi `USER_stok`. Pakai **password kuat**, catat.
   Hindari kutip `'` `"` dan backslash `\`.
3. **Add User To Database**: pilih user + database → centang **ALL PRIVILEGES** →
   **Make Changes**.

Catat 3 nilai ini untuk `config/local.php`: `USER_stokproyek`, `USER_stok`, password.

### C — Pasang aplikasi lewat Terminal cPanel · ~15–20 menit

Buka cPanel → **Terminal** (bagian *Advanced*). Prompt mulai di `/home/USER_KAMU`.

#### C1. Ambil kode

```bash
cd ~
git clone https://github.com/adedian/Stok-proyek.git stok-proyek
cd stok-proyek
php -v          # cek versi; lihat catatan PHP di atas
```

> **Kalau Document Root tadi terpaksa `public_html/stok`** (B1): buat symlink supaya
> subdomain menunjuk ke folder `public/` aplikasi:
> ```bash
> rm -rf ~/public_html/stok
> ln -s ~/stok-proyek/public ~/public_html/stok
> ```

#### C2. Install dependency (Composer)

```bash
composer --version || alias composer='/opt/cpanel/composer/bin/composer'
composer install --no-dev --optimize-autoloader --no-interaction
```

Kalau gagal karena versi PHP CLI: `PHP /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader --no-interaction`.

Cek: folder `vendor/` terisi, tidak ada error merah.

#### C3. Tulis `config/local.php`

```bash
cp config/local.example.php config/local.php
nano config/local.php
```

Isi minimal:

```php
return [
    'app_env'    => 'production',
    'db_host'    => 'localhost',
    'db_name'    => 'USER_stokproyek',
    'db_user'    => 'USER_stok',
    'db_pass'    => 'PASSWORD_DARI_B3',
    'db_charset' => 'utf8mb4',

    'mysqldump_path'   => 'mysqldump',
    'ghostscript_path' => '',
];
```

Simpan: `Ctrl+O` → Enter → `Ctrl+X`.

#### C4. Import database

**Punya dump lengkap** (dari A2) — upload dulu file `db_stok_proyek.sql` ke
`~/stok-proyek/` lewat cPanel **File Manager** (atau `scp` kalau SSH aktif), lalu:

```bash
mysql -u USER_stok -p USER_stokproyek < ~/stok-proyek/db_stok_proyek.sql
PHP bin/migrate.php --baseline     # tandai migrasi lama = sudah jalan
PHP bin/migrate.php                # jalankan yang benar-benar baru
rm ~/stok-proyek/db_stok_proyek.sql
```

**Mulai bersih** (tanpa data uji):

```bash
mysql -u USER_stok -p USER_stokproyek < database/schema.sql
PHP bin/migrate.php --baseline
PHP bin/migrate.php
```

> File `.sql` besar (> 50 MB) tidak bisa lewat phpMyAdmin — pakai perintah `mysql`
> di atas.

#### C5. Izin folder tulis

```bash
mkdir -p public/uploads storage/backups logs
chmod -R 755 public/uploads storage logs
```

Di shared hosting PHP jalan sebagai user kamu sendiri, jadi 755 sudah cukup —
tidak perlu `chown www-data`.

### D — HTTPS, akun, & pengujian · ~10 menit

#### D1. HTTPS (AutoSSL)

cPanel → **SSL/TLS Status** → cari `stok.hexamultienergi.com`. Biasanya sudah
**hijau** dalam beberapa menit. Kalau belum: centang subdomainnya → **Run AutoSSL**.
Aplikasi memaksa `https://` sendiri di mode produksi, jadi setelah sertifikat aktif
semua `http://` otomatis dialihkan.

Buka **`https://stok.hexamultienergi.com`** → halaman login + ikon gembok. 🎉

#### D2. Amankan akun bawaan

Ada 2 akun Super Admin: **`ade`** (punyamu) dan **`admin`** (default, password lemah).
Di **Terminal**:

```bash
PHP ~/stok-proyek/bin/reset_user_password.php admin --yes
```

Catat password acak baru yang dicetak. Atau login sebagai `ade` → **User
Management** → nonaktifkan `admin`.

#### D3. Checklist pengujian

Login sebagai `ade`, coba satu per satu:

- [ ] Login berhasil, dashboard tampil.
- [ ] Buka tiap menu (PO, Pembayaran, Kas, Penerimaan, Pengeluaran, Stok & Opname,
      Invoice Keluar, Laporan, Master Data, User Management, Pengaturan Sistem) —
      tidak ada error / halaman putih.
- [ ] **Upload foto**: Penerimaan Barang → Foto Barang, atau Pengaturan Akun →
      Foto Profil → tersimpan & tampil (juga di avatar pojok kanan atas).
- [ ] **Upload PDF** (mis. bukti bayar / invoice) → tersimpan & bisa dibuka.
- [ ] **Laporan** → **Export Excel** & **Export PDF** → file ter-*download* & kebuka.
- [ ] Buka dari **HP**: login rapi, tidak ada geser horizontal, tabel jadi kartu,
      form & modal enak dipakai; bisa **Add to Home screen** (PWA).
- [ ] **Terminal**: `tail -n 50 ~/stok-proyek/logs/error.log` → tidak ada error baru.

#### D4. Cron pembersih file lama

cPanel → **Cron Jobs** → **Add New Cron Job** → *Once Per Week* (`30 3 * * 0`):

```
30 3 * * 0 /opt/cpanel/ea-php82/root/usr/bin/php /home/USER_KAMU/stok-proyek/bin/cleanup.php >> /home/USER_KAMU/stok-cleanup.log 2>&1
```

(ganti `ea-php82` & `USER_KAMU`). Tiap Minggu 03:30: hapus backup DB `.sql` > 30
hari **tapi sisakan 7 terbaru**, arsip `logs/error.log.*` > 30 hari, sisa file
sementara Dompdf & `.gs` (kompres PDF) yang nyangkut. Uji dulu:
`PHP ~/stok-proyek/bin/cleanup.php --dry-run`.

---

## 4. Kapasitas penyimpanan & kapan pindah ke VPS

Kondisi kuota cPanel bersifat **berbagi** dengan email & situs lain di akun yang
sama. Aplikasi ini sendiri **hemat disk**:

| Yang tumbuh | Perkiraan | Catatan |
|---|---|---|
| Database (+ activity log) | ~150–400 MB / tahun | baris transaksi kecil |
| Foto upload | ~200–300 MB / tahun | auto-kompres GD ke ≤ 1920px |
| PDF upload (bukti/invoice) | ~300–800 MB / tahun | **tak dikompres** di cPanel (Ghostscript biasanya tak ada); ini variabel terbesar |
| Export PDF/Excel | 0 | di-stream ke browser, tidak disimpan |
| Log | ~0 | dirotasi + cron cleanup |
| **Total aplikasi** | **± 4–8 GB / 5 tahun** | |

**3 hal yang membuat kuota cepat penuh (bukan aplikasinya):**

1. **Backup jangan disimpan di dalam akun.** `uploads.tgz` × belasan salinan bisa
   lebih besar dari aplikasinya. Unduh keluar (lihat [Bagian 6](#6-backup)).
2. **Email** biasanya pemakan kuota terbesar & naik terus — pantau terpisah.
3. **PDF scan besar** (mendekati 25 MB) → turunkan resolusi pemindai, atau minta
   user unggah foto (yang auto-kompres) daripada PDF.

**Pantau:** cPanel → **Disk Usage**. Set peringatan di ~80% kuota.

**Pindah ke VPS ([FASE 2](#7-fase-2--pindah-ke-vps-hostinger)) kalau salah satu ini:**

- Folder akun mendekati batas kuota dan email/situs lain tidak bisa dikecilkan lagi.
- Butuh Ghostscript (kompres PDF), `proc_open` (Backup dari menu), atau kontrol
  cron/PHP penuh.
- Performa: banyak user bersamaan mulai terasa lambat di shared hosting.

Ukur dulu laju pertumbuhan nyata setelah ~6 bulan pakai (`du -sh ~/stok-proyek/public/uploads`
dan ukuran DB di cPanel), baru putuskan ukuran VPS.

---

## 5. Kerja sehari-hari (update aplikasi)

Kamu tetap ngoding di laptop. **Di laptop (PowerShell):**

```powershell
cd C:\xampp\htdocs\stok-proyek
git add -A
git commit -m "penjelasan singkat perubahan"
git push origin master
```

**Terapkan di hosting cPanel** — buka **Terminal**:

```bash
cd ~/stok-proyek
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
PHP bin/migrate.php
PHP bin/make_pwa_icons.php    # hanya kalau ikon PWA berubah
```

- Aman diulang. Tidak perlu reload Apache di shared hosting.
- Kalau perubahan belum kelihatan: cPanel → **Select PHP Version** buka-tutup, atau
  tunggu beberapa menit (OPcache biasanya refresh sendiri).
- **Kalau `git pull` ditolak** (`local changes would be overwritten`) — ada yang
  mengedit langsung di server:
  ```bash
  cd ~/stok-proyek && git checkout -- . && git clean -fd && git pull --ff-only
  ```

> Claude Code di laptop bisa menuntunmu menempel perintah-perintah Terminal ini
> setelah selesai memperbaiki kode.

---

## 6. Backup

Aplikasi ini **tidak menghapus data**, tapi backup tetap wajib. Pilih salah satu
(idealnya keduanya):

### 6.1 Backup bawaan cPanel

cPanel → **Backup** / **Backup Wizard** → **Download a Full Account Backup**, atau
minta hosting mengaktifkan **backup terjadwal**. Ini mencakup DB + semua file.

### 6.2 Backup manual dari Terminal (lalu unduh keluar)

```bash
mkdir -p ~/backups-stok
D=$(date +%F_%H%M)
mysqldump -u USER_stok -p'PASSWORD' USER_stokproyek | gzip -9 > ~/backups-stok/db_$D.sql.gz
tar -C ~/stok-proyek/public -czf ~/backups-stok/uploads_$D.tgz uploads
ls -lh ~/backups-stok
```

Bisa dijadwalkan lewat **Cron Jobs**. **Penting:** unduh `~/backups-stok/` ke
laptop/Google Drive berkala dan **hapus yang lama dari server** — backup di dalam
akun yang sama ikut memakan kuota dan hilang kalau akunnya bermasalah.

> Menu **Pengaturan Sistem → Backup Database** di aplikasi hanya jalan kalau
> `proc_open` tidak dimatikan hosting. Kalau gagal, pakai cara di atas.

---

## 7. FASE 2 — Pindah ke VPS Hostinger

Dilakukan **nanti**, kalau [Bagian 4](#4-kapasitas-penyimpanan--kapan-pindah-ke-vps)
sudah terpenuhi. Downtime nyata biasanya **< 30 menit**. Skrip di `deploy/` yang
melakukan hampir semuanya.

### 7.1 Sewa & siapkan VPS

- **Hostinger** → VPS (KVM), OS **Ubuntu 24.04** (atau 22.04). Ambil paket dengan
  disk ≥ 2× estimasi 5 tahun (mis. 80–100 GB) + RAM ≥ 2 GB.
- Di panel Hostinger: catat **IP VPS**, pastikan **port 22 / 80 / 443** terbuka
  (Hostinger biasanya sudah; tidak seperti Oracle yang perlu buka manual).
- Tambahkan **kunci SSH** kamu (`~/.ssh/id_ed25519.pub` dari laptop; buat dengan
  `ssh-keygen -t ed25519` kalau belum ada).

### 7.2 Pasang aplikasi di VPS

SSH ke VPS dari PowerShell: `ssh root@IP_VPS` (Hostinger sering pakai user `root`;
kalau `ubuntu`, sesuaikan). Lalu:

```bash
sudo apt-get update -y && sudo apt-get install -y git
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
```

`setup.sh` menanyakan 3 hal, lalu jalan **± 3–8 menit** (`==> 1/7` … `==> 7/7`):

| Pertanyaan | Isi |
|---|---|
| Subdomain | `stok.hexamultienergi.com` (sama seperti sekarang) |
| Password DB `stok` | password kuat baru, catat |
| Path dump `.sql` | **Enter (kosong)** — data diisi di 7.3 |

Yang dikerjakan: pasang **Apache, MariaDB, PHP 8, ekstensi, Ghostscript, Composer,
git** → buat DB + user → `git clone` ke **`/var/www/stok`** →
`composer install --no-dev` → tulis `config/local.php` (`app_env=production`) →
set izin folder → buat **VirtualHost** (`DocumentRoot /var/www/stok/public`).

### 7.3 Pindahkan data dari cPanel ke VPS

Di **Terminal cPanel**, buat arsip terbaru:

```bash
cd ~
D=$(date +%F)
mysqldump -u USER_stok -p'PASSWORD' USER_stokproyek > db_$D.sql
tar -C ~/stok-proyek/public -czf uploads_$D.tgz uploads
```

Unduh `db_$D.sql` + `uploads_$D.tgz` ke laptop (cPanel **File Manager** → Download),
lalu kirim ke VPS & pulihkan:

```powershell
scp db_2026-09-09.sql uploads_2026-09-09.tgz root@IP_VPS:/tmp/
```

```bash
# di SSH VPS
mysql db_stok_proyek < /tmp/db_2026-09-09.sql
tar -C /var/www/stok/public -xzf /tmp/uploads_2026-09-09.tgz
cd /var/www/stok
php bin/migrate.php --baseline
php bin/migrate.php
sudo chown -R www-data:www-data public/uploads storage logs
```

### 7.4 HTTPS di VPS

```bash
sudo certbot --apache -d stok.hexamultienergi.com
```

(Kalau `ServerName` di `/etc/apache2/sites-available/stok.conf` belum
`stok.hexamultienergi.com`, betulkan dulu → `sudo systemctl reload apache2`.)

### 7.5 Verifikasi VPS **sebelum** ganti DNS

Di laptop, **Notepad sebagai Administrator** →
`C:\Windows\System32\drivers\etc\hosts` → tambah baris:

```
IP_VPS   stok.hexamultienergi.com
```

Buka `https://stok.hexamultienergi.com` → jalankan **checklist D3**. Semua OK →
**hapus lagi baris `hosts`** itu.

### 7.6 Alihkan trafik

- Turunkan **TTL** A record `stok` ke `300` di panel DNS `hexamultienergi.com`
  beberapa jam sebelumnya.
- Kalau subdomain selama ini dikelola cPanel: buat **A record manual** `stok` →
  `IP_VPS` di panel DNS domain, dan hapus subdomain lama dari cPanel setelah pindah.
- Dalam ± 5 menit semua pengguna pindah ke VPS.

### 7.7 Setelah pindah

- Kerja sehari-hari jadi: `ssh root@IP_VPS "bash /var/www/stok/deploy/update.sh"`.
- Backup: aktifkan cron `deploy/backup.sh` + `deploy/cleanup.sh` (lihat
  [`deploy/`](deploy/) dan komentar di tiap skrip), **tarik hasilnya keluar VPS**.
- Pantau 1–2 hari, lalu naikkan TTL A record kembali ke `3600`.

> **Alternatif VPS gratis untuk uji coba:** Oracle Cloud Always Free / AWS free
> tier. Langkahnya sama (`setup.sh`), hanya Oracle perlu membuka port 80/443 manual
> di **Security List** + `iptables` OS. Lihat riwayat git README ini kalau perlu
> panduan Oracle lengkap.

---

## 8. Peta folder & perintah penting

### Di hosting cPanel (FASE 1)

```
/home/USER/stok-proyek/          ← aplikasi (git clone)
├── config/local.php             ← kredensial DB (TIDAK di git)
├── public/                      ← Document Root subdomain
│   ├── uploads/                 ← foto/file upload (backup manual, tidak di git)
│   ├── manifest.webmanifest / sw.js   ← PWA
├── logs/error.log               ← log error aplikasi
├── storage/backups/             ← backup dari menu Pengaturan Sistem
├── bin/migrate.php              ← runner migrasi DB
├── bin/reset_user_password.php  ← reset password user (darurat)
└── bin/cleanup.php              ← pembersih backup/log/temp lama
/home/USER/backups-stok/         ← backup manual dari Terminal (unduh & hapus berkala)
```

Perintah sering dipakai (Terminal cPanel, `PHP` = binari PHP 8 — lihat catatan di Bagian 3):

```bash
cd ~/stok-proyek
git pull --ff-only && composer install --no-dev -o && PHP bin/migrate.php   # update
PHP bin/migrate.php --status         # migrasi mana yang belum jalan
tail -f ~/stok-proyek/logs/error.log # log error real-time
PHP bin/reset_user_password.php <username> --yes   # reset password darurat
```

### Di VPS (FASE 2)

```
/var/www/stok/                          ← aplikasi
/etc/apache2/sites-available/stok.conf  ← VirtualHost
/var/log/apache2/stok_error.log         ← log Apache
/var/backups/stok/                      ← backup terjadwal (cron)
```

```bash
sudo systemctl status apache2 mariadb
sudo tail -f /var/log/apache2/stok_error.log /var/www/stok/logs/error.log
cd /var/www/stok && php bin/migrate.php --status
ssh root@IP_VPS "bash /var/www/stok/deploy/update.sh"     # update rutin
bash /var/www/stok/deploy/backup.sh                        # backup manual
```

---

## 9. Troubleshooting

### cPanel (FASE 1)

| Gejala | Penyebab & solusi |
|---|---|
| Subdomain buka **file listing** / halaman hosting default | Document Root belum menunjuk ke `~/stok-proyek/public`. Betulkan di **Domains** → *Manage*, atau pakai symlink (C1). |
| Halaman putih / **HTTP 500** | `tail -n 50 ~/stok-proyek/logs/error.log`. Paling sering: `config/local.php` salah kredensial DB, `composer install` belum sukses, atau versi PHP subdomain < 8.1 (**MultiPHP Manager**). |
| CSS/JS tidak muncul, sub-halaman **404** | `mod_rewrite` / `AllowOverride` — hampir selalu sudah aktif di cPanel; kalau tidak, hubungi hosting. Pastikan Document Root = `.../public`. |
| **Export PDF / Excel** error | `composer install --no-dev -o` di Terminal belum sukses (folder `vendor/` kosong). |
| **PDF upload tidak mengecil** | Normal di shared hosting — Ghostscript / `proc_open` tidak tersedia. PDF tetap tersimpan utuh. Kompresi aktif nanti di VPS. |
| Menu **Backup Database** gagal | `proc_open` dimatikan hosting → pakai **phpMyAdmin → Export** atau [Bagian 6.2](#62-backup-manual-dari-terminal-lalu-unduh-keluar). |
| Upload gagal untuk file agak besar | Naikkan `upload_max_filesize` / `post_max_size` di **MultiPHP INI Editor** (B2). |
| Migrasi DB error | `PHP bin/migrate.php --status` untuk detail; perbaiki penyebab lalu `PHP bin/migrate.php`. Kalau DB diimport dari dump lama & belum pernah `--baseline`, jalankan `PHP bin/migrate.php --baseline` sekali. |
| `composer` command not found | Pakai `/opt/cpanel/composer/bin/composer`, atau `PHP /opt/cpanel/composer/bin/composer ...`. |
| Kuota disk penuh | cPanel → **Disk Usage**. Biasanya email, bukan aplikasi. Lihat [Bagian 4](#4-kapasitas-penyimpanan--kapan-pindah-ke-vps). |
| Login **kena kunci** terus | Normal setelah 5 gagal/akun atau 8 gagal/IP dalam 15 menit. Tunggu, atau `PHP bin/reset_user_password.php <username> --yes`. |
| **PWA** tidak bisa di-install di HP | Belum HTTPS (cek **SSL/TLS Status**), atau `sw.js` balas 404 → `curl -I https://stok.hexamultienergi.com/sw.js` harus `200`. |
| Sudah `git pull` tapi HP masih tampilan lama | Cache service worker. Di laptop: naikkan `VERSION` di `public/sw.js`, commit, push, `git pull` di hosting, lalu di HP tutup-buka app 2×. |

### VPS (FASE 2)

| Gejala | Penyebab & solusi |
|---|---|
| SSH `Connection timed out` / `Permission denied (publickey)` | IP/kunci salah, atau firewall provider. Pakai `-i` ke file kunci yang benar; cek user (`root` / `ubuntu`). |
| `http://IP_VPS` tidak kebuka padahal Apache `active` | Firewall provider / OS. Di Oracle: buka **Security List** + `iptables`. Hostinger biasanya sudah terbuka. |
| `certbot` gagal *Timeout* / *NXDOMAIN* | DNS belum menunjuk ke IP VPS (`nslookup stok.hexamultienergi.com`), Cloudflare masih "Proxied" (set "DNS only"), atau port 80 tertutup. |
| Halaman putih / **HTTP 500** | `sudo tail -f /var/log/apache2/stok_error.log /var/www/stok/logs/error.log`. Sering: `config/local.php` salah, `composer install` gagal. |
| Upload foto gagal / tidak tampil | `sudo chown -R www-data:www-data /var/www/stok/public/uploads /var/www/stok/storage /var/www/stok/logs && sudo chmod -R 775` folder-folder itu. |
| Menu **Backup** gagal | `mysqldump` tidak di PATH → set `'mysqldump_path' => 'mysqldump'` di `config/local.php`. |
| `git pull` di VPS ditolak | Ada edit langsung di server → `cd /var/www/stok && git checkout -- . && git clean -fd && bash deploy/update.sh`. |
