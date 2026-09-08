# Sistem Kontrol Stok Proyek — PT. Hexa Multi Energi

Aplikasi web internal untuk kontrol stok proyek: Purchase Order, Penerimaan &
Pengeluaran Barang, Stok Opname, Kas, Invoice Keluar / Surat Jalan / Tanda Terima,
Laporan, dan Master Data. Bisa di-*install* sebagai aplikasi di HP (PWA).

- **Stack**: PHP 8.1+ (tanpa framework, MVC sendiri) · MySQL / MariaDB · Apache
  (`.htaccess` + `mod_rewrite`) · Bootstrap 5 · Composer (Dompdf untuk PDF,
  PhpSpreadsheet untuk Excel).
- **Repo**: `https://github.com/adedian/Stok-proyek` (publik).

---

## Daftar isi

- [1. Menjalankan di komputer lokal (XAMPP)](#1-menjalankan-di-komputer-lokal-xampp)
- [2. Cara kerja deploy (baca dulu)](#2-cara-kerja-deploy-baca-dulu)
- [3. LANGKAH DEPLOY — dari nol sampai online](#3-langkah-deploy--dari-nol-sampai-online)
  - [A. Persiapan di laptop](#a--persiapan-di-laptop-windows--10-menit)
  - [B. Buat VPS gratis (Oracle Cloud)](#b--buat-vps-gratis-oracle-cloud-always-free--2030-menit)
  - [C. Deploy aplikasi ke VPS](#c--deploy-aplikasi-ke-vps--1015-menit)
  - [D. Subdomain + HTTPS](#d--subdomain--https--1020-menit)
  - [E. Finalisasi & pengujian](#e--finalisasi--pengujian--10-menit)
- [4. Kerja sehari-hari (update aplikasi)](#4-kerja-sehari-hari-update-aplikasi)
- [5. Backup otomatis](#5-backup-otomatis)
- [6. Pindah dari VPS gratis ke VPS berbayar](#6-pindah-dari-vps-gratis-ke-vps-berbayar)
- [7. Peta folder & perintah penting di VPS](#7-peta-folder--perintah-penting-di-vps)
- [8. Troubleshooting](#8-troubleshooting)

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
| **Konfigurasi server** | 1 file `config/local.php` (tidak masuk git) | dibuat otomatis oleh `deploy/setup.sh` |
| **Data** | Database MySQL + folder `public/uploads/` | 1 file `.sql` + 1 file `.tgz` |

Konsekuensinya:

- **Pindah ke VPS lain** = jalankan `deploy/setup.sh` di VPS baru + salin data + ganti DNS. Sudah ada skripnya.
- **Kamu tetap ngoding di laptop** (XAMPP + Claude Code). VPS cuma *menerima* hasil:
  `git push` di laptop → `bash deploy/update.sh` di VPS.
- **Aplikasi di VPS JANGAN diedit langsung.** Semua perubahan lewat git.

Alur besar:

```
┌─────────────┐   git push    ┌──────────┐   git pull (update.sh)   ┌───────────┐
│  LAPTOP     │ ────────────▶ │  GitHub  │ ───────────────────────▶ │   VPS     │
│  XAMPP +    │               │  master  │                          │  Apache + │
│  Claude Code│ ◀──────────── │          │                          │  MySQL    │
└─────────────┘   git pull    └──────────┘                          └───────────┘
```

Istilah singkat:

- **VPS** = komputer server sewaan di internet.
- **SSH** = cara masuk ke VPS lewat terminal (dari PowerShell di Windows).
- **DNS / A record** = "buku alamat" yang menghubungkan `stok.domain.com` → IP VPS.
- **Subdomain** = bagian `stok.` di depan domain kamu.

Skrip pendukung di folder [`deploy/`](deploy/):

| Skrip | Kapan dipakai |
|---|---|
| `setup.sh` | **1×** di VPS baru — pasang semua + deploy pertama |
| `update.sh` | tiap ada perubahan kode (`git push`) |
| `backup.sh` | dijadwalkan cron harian |
| `cleanup.sh` | dijadwalkan cron mingguan — buang backup DB & file lama |
| `migrate-server.sh` | saat pindah VPS lama → VPS baru |

---

## 3. LANGKAH DEPLOY — dari nol sampai online

Target akhir: `https://stok-test.hexamultienergi.com` (uji coba di VPS gratis),
lalu nanti `https://stok.hexamultienergi.com` di VPS berbayar.

Total waktu pertama kali: **± 1–2 jam** (paling lama nunggu daftar Oracle & DNS).

---

### A — Persiapan di laptop (Windows) · ~10 menit

#### A1. Cek yang harus sudah ada

- [x] Aplikasi jalan di XAMPP (`http://localhost/stok-proyek/public/`).
- [x] Punya akun **GitHub** & repo `adedian/Stok-proyek`.
- [ ] Bisa masuk ke **panel DNS** domain `hexamultienergi.com`
      (Cloudflare / cPanel / tempat beli domain).
- [ ] **Kartu debit/kredit** untuk verifikasi identitas Oracle
      (Always Free **tidak ditagih**; hanya penahanan ± Rp 1 yang dikembalikan).
- Windows 10/11 sudah punya `ssh`, `scp`, `ssh-keygen` bawaan — semua dijalankan
  lewat **PowerShell** (klik kanan Start → **Terminal** / **Windows PowerShell**).

#### A2. Pastikan kode terbaru sudah di GitHub

```powershell
cd C:\xampp\htdocs\stok-proyek
git status
```

- **Kalau muncul** `nothing to commit, working tree clean` → lanjut A3.
- **Kalau ada perubahan** → commit & push dulu:

```powershell
git add -A
git commit -m "siap deploy"
git push origin master
```

Cek: `git push` membalas `Everything up-to-date` atau menampilkan hash commit
terkirim (mis. `caf89fd..9983825  master -> master`).

#### A3. Buat dump (salinan) database lokal

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --databases db_stok_proyek --add-drop-database --result-file=C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql
```

- Perintah ini **tidak menampilkan output** kalau berhasil.
- Cek: file `C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql` muncul, ukuran > 100 KB.
- Pakai `--result-file=` (bukan `>`) supaya file tidak rusak oleh encoding PowerShell.
- File ini berisi **skema + data + riwayat migrasi**. Sudah otomatis di-*ignore*
  git (lihat `.gitignore`), jadi tidak akan ke-commit.

> **Mau produksi mulai bersih tanpa data uji?** Lewati langkah ini. Nanti di C3
> biarkan pertanyaan path dump kosong — `setup.sh` akan bikin DB kosong lalu kamu
> jalankan `php bin/migrate.php` manual (butuh import `database/schema.sql` dulu).

#### A4. Buat kunci SSH (sekali seumur hidup)

```powershell
ssh-keygen -t ed25519 -C "deploy-stok"
```

- Tekan **Enter 3×** untuk pakai lokasi default (`C:\Users\NAMA\.ssh\id_ed25519`)
  tanpa passphrase.
- Cek: muncul `Your public key has been saved in ...id_ed25519.pub`.

Tampilkan kunci **publik** (yang nanti ditempel ke Oracle):

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
```

Blok seluruh barisnya (mulai `ssh-ed25519 AAAA... deploy-stok`) lalu **copy**.

---

### B — Buat VPS gratis (Oracle Cloud Always Free) · ~20–30 menit

> Alternatif kalau Oracle susah: **AWS EC2 / Lightsail free tier** (gratis 12 bulan)
> atau VPS berbayar murah. Langkah C–H sama, hanya cara buat VM & buka port beda.

#### B1. Daftar akun

1. Buka **<https://www.oracle.com/cloud/free/>** → **Start for free**.
2. Isi email → verifikasi. Negara **Indonesia**. Verifikasi nomor HP (SMS).
   Verifikasi kartu.
3. Pilih **Home Region** yang dekat: **Singapore** (`ap-singapore-1`) atau
   **Jakarta** kalau tersedia.
   ⚠️ **Region tidak bisa diganti** setelah akun jadi.
4. Tunggu email **"Your Oracle Cloud Infrastructure account is ready"**
   (beberapa menit sampai beberapa jam).

#### B2. Buat instance (VM) Ubuntu

1. Login **<https://cloud.oracle.com>**.
2. Menu **☰** (kiri atas) → **Compute** → **Instances** → **Create instance**.
3. **Name**: `stok-vps`.
4. Bagian **Image and shape** → klik **Edit**:
   - **Image**: **Change image** → **Canonical Ubuntu** → **24.04** (atau 22.04) → **Select image**.
   - **Shape**: **Change shape**:
     - Tab **Ampere** → `VM.Standard.A1.Flex` → set **OCPUs = 1**, **Memory = 6 GB** → **Select shape**.
       *(Always Free total: 4 OCPU / 24 GB. Pakai 1/6 dulu sudah cukup.)*
     - Kalau muncul **"Out of capacity"** → tab **AMD** → `VM.Standard.E2.1.Micro` (Always Free, lebih kecil tapi cukup).
5. Bagian **Networking**: biarkan default (buat VCN & subnet baru). Pastikan
   **Assign a public IPv4 address** = **Yes**.
6. Bagian **Add SSH keys** → pilih **Paste public keys** → **tempel** isi
   `id_ed25519.pub` dari A4.
7. Klik **Create**. Tunggu status kotak besar berubah dari **PROVISIONING** (oranye)
   → **RUNNING** (hijau).
8. **Catat "Public IP address"** (mis. `152.42.xx.xx`). Di panduan ini disebut **`IP_VPS`**.

#### B3. Buka port 80 & 443 — WAJIB, di **2 tempat**

Oracle memblokir port kecuali 22 di **dua lapisan**: firewall cloud (Security List)
dan firewall di dalam OS. **Keduanya** harus dibuka.

**(a) Security List (di Oracle Console):**

1. Dari halaman instance → klik nama **Virtual cloud network** (link biru).
2. Menu kiri → **Security** → **Security Lists** → klik **Default Security List for ...**.
3. **Add Ingress Rules** → isi baris pertama:
   - Source Type: **CIDR**
   - Source CIDR: `0.0.0.0/0`
   - IP Protocol: **TCP**
   - Destination Port Range: `80`
4. Klik **+ Another Ingress Rule** → isi lagi dengan port `443`.
5. **Add Ingress Rules** (simpan).

**(b) Firewall OS** — dijalankan **nanti** setelah bisa SSH (langkah C1):

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

Cek: `sudo netfilter-persistent save` membalas `run-parts: executing .../netfilter-persistent save`.

> **Gejala kalau langkah (b) terlewat:** Apache jalan, `http://IP_VPS` tidak kebuka,
> dan `certbot` gagal dengan *Timeout during connect*.

---

### C — Deploy aplikasi ke VPS · ~10–15 menit

#### C1. Masuk ke VPS lewat SSH

Di **PowerShell**:

```powershell
ssh -i $env:USERPROFILE\.ssh\id_ed25519 ubuntu@IP_VPS
```

- Ganti `IP_VPS` dengan angka IP dari B2.
- Pertama kali muncul `Are you sure you want to continue connecting (yes/no)?` → ketik **`yes`** → Enter.
- Cek: prompt berubah jadi `ubuntu@stok-vps:~$` — **kamu sudah di dalam VPS**.
- User default Oracle Ubuntu = **`ubuntu`** (punya `sudo` tanpa password).

**Sekarang jalankan firewall OS dari B3-(b):**

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

#### C2. Kirim dump database ke VPS

Buka **PowerShell BARU** (jendela SSH biarkan terbuka):

```powershell
scp -i $env:USERPROFILE\.ssh\id_ed25519 C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql ubuntu@IP_VPS:/tmp/
```

Cek: muncul progress `db_stok_proyek.sql   100%  ...  ...KB/s`. Selesai kalau `100%`.

#### C3. Jalankan skrip setup

Kembali ke jendela **SSH**. Jalankan 3 baris ini:

```bash
sudo apt-get update -y && sudo apt-get install -y git
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
```

Skrip akan **menanyakan 3 hal** (contoh tampilan):

```
Subdomain (mis. stok-test.hexamultienergi.com): stok-test.hexamultienergi.com
Password untuk user DB 'stok': ********************
Path file dump .sql untuk di-restore (Enter = lewati): /tmp/db_stok_proyek.sql
```

| Pertanyaan | Isi |
|---|---|
| Subdomain | `stok-test.hexamultienergi.com` |
| Password DB `stok` | password kuat, **catat di tempat aman**. Pakai huruf + angka + simbol aman (`- _ . ! @ # %`). **Hindari** kutip `'` `"` dan backslash `\` (bisa merusak skrip). |
| Path dump | `/tmp/db_stok_proyek.sql` (atau **Enter** kalau mau DB kosong) |

Lalu skrip jalan otomatis **± 3–8 menit**, menampilkan langkah `==> 1/7` sampai `==> 7/7`:

1. update sistem + pasang **Apache, MariaDB, PHP 8, ekstensi, git**
2. aktifkan modul Apache (`rewrite`, `headers`, `ssl`, `mime`)
3. pasang **Composer**
4. buat database `db_stok_proyek` + user `stok`
5. `git clone` aplikasi ke **`/var/www/stok`** + `composer install --no-dev`
6. tulis **`config/local.php`** (`app_env = production`) + restore dump + migrasi
7. set izin folder (`uploads`, `storage`, `logs`) + buat **VirtualHost**
   (`DocumentRoot /var/www/stok/public`) + reload Apache

Di akhir muncul:

```
============================================================
 SELESAI (HTTP).  Langkah terakhir:
 1) Arahkan A record  stok-test.hexamultienergi.com  ->  IP VPS ini.
 ...
============================================================
```

**Tes cepat:** buka browser laptop ke `http://IP_VPS`.
Cek: **halaman login muncul** (tampilan mungkin agak beda karena diakses lewat IP —
itu normal, akan benar setelah pakai subdomain di Bagian D).

> Kalau `http://IP_VPS` tidak kebuka → cek firewall OS (C1) & Security List (B3-a).

---

### D — Subdomain + HTTPS · ~10–20 menit

#### D1. Arahkan subdomain ke VPS

Masuk ke **panel DNS** `hexamultienergi.com`. Tambah 1 record:

| Type | Name / Host | Value / Points to | TTL | Proxy (Cloudflare) |
|---|---|---|---|---|
| `A` | `stok-test` | `IP_VPS` | `300` (5 menit) | **DNS only** (awan abu-abu) |

> **Cloudflare**: kolom proxy **harus "DNS only"** dulu supaya Let's Encrypt bisa
> verifikasi. Boleh dinyalakan lagi ("Proxied") setelah HTTPS aktif.

Cek propagasi dari **PowerShell**:

```powershell
nslookup stok-test.hexamultienergi.com
```

Ulangi tiap beberapa menit sampai baris **`Address:` menampilkan `IP_VPS`** kamu
(biasanya 1–15 menit).

#### D2. Pasang SSL (HTTPS gratis Let's Encrypt)

Di jendela **SSH**:

```bash
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d stok-test.hexamultienergi.com
```

Jawab pertanyaan certbot:

- `Enter email address` → email kamu (untuk notifikasi kedaluwarsa).
- `Agree to Terms of Service` → **`Y`**.
- `Share email with EFF` → `Y` / `N` bebas.
- Kalau ditanya redirect → pilih **`2` (Redirect)** — semua `http://` dialihkan ke `https://`.

Cek: muncul `Congratulations! ... https://stok-test.hexamultienergi.com`.
Perpanjangan otomatis sudah aktif — verifikasi dengan `sudo systemctl status certbot.timer`.

**Buka `https://stok-test.hexamultienergi.com`** → halaman login rapi + ikon gembok. 🎉

---

### E — Finalisasi & pengujian · ~10 menit

#### E1. Amankan akun bawaan

Ada 2 akun Super Admin: **`ade`** (punyamu) dan **`admin`** (default, password lemah `admin123`).
Di **SSH**:

```bash
php /var/www/stok/bin/reset_user_password.php admin --yes
```

Cek: skrip mencetak **password acak baru untuk `admin` — catat sekali ini saja**.
Atau: login pakai `ade`, buka **User Management**, **nonaktifkan** akun `admin`.

#### E2. Checklist pengujian

Buka `https://stok-test.hexamultienergi.com`, login pakai `ade`, coba satu per satu:

- [ ] Login berhasil, dashboard tampil.
- [ ] Buka tiap menu (PO, Pembayaran, Kas, Penerimaan, Pengeluaran, Stok & Opname,
      Invoice Keluar, Laporan, Master Data, User Management, Pengaturan Sistem) —
      tidak ada error / halaman putih.
- [ ] **Upload foto**: Penerimaan Barang → Foto Barang, atau Pengaturan Akun →
      Foto Profil → tersimpan & tampil (juga muncul di avatar pojok kanan atas).
- [ ] **Laporan** → **Export Excel** & **Export PDF** → file ter-*download* & kebuka.
- [ ] **Pengaturan Sistem** → **Backup Database** → file backup terbuat.
- [ ] Buka dari **HP** (browser): login rapi, tidak ada geser horizontal, tabel
      jadi kartu, form & modal enak dipakai.
- [ ] Di **SSH**: `tail -n 50 /var/www/stok/logs/error.log` → kosong / tidak ada
      error baru.

#### E3. Install sebagai aplikasi di HP (PWA)

Syarat: HTTPS sudah aktif (D2).

- **Android (Chrome)**: buka situs → menu **⋮** → **Add to Home screen** /
  banner **Install app**.
- **iPhone (Safari)**: buka situs → tombol **Share** (kotak + panah ke atas) →
  **Add to Home Screen**.

Hasil: ikon **"Stok Proyek"** di layar HP, dibuka jalan **layar penuh** seperti app
biasa; ada halaman "Tidak ada koneksi" saat internet putus.

**Cek teknis (opsional, Chrome laptop):** `F12` → tab **Application** →
**Manifest** (ikon & nama tampil) + **Service Workers** (status *activated and running*).

---

## 4. Kerja sehari-hari (update aplikasi)

Alur **tidak berubah** dari sekarang. Kamu tetap ngoding di laptop.

**Di laptop (PowerShell):**

```powershell
cd C:\xampp\htdocs\stok-proyek
git add -A
git commit -m "penjelasan singkat perubahan"
git push origin master
```

**Terapkan di VPS** — pilih salah satu:

```powershell
# Cara 1: dari laptop, tanpa login manual
ssh -i $env:USERPROFILE\.ssh\id_ed25519 ubuntu@IP_VPS "bash /var/www/stok/deploy/update.sh"
```

```bash
# Cara 2: sudah di dalam SSH
bash /var/www/stok/deploy/update.sh
```

`update.sh` menjalankan: `git pull --ff-only` → `composer install` →
`php bin/migrate.php` → regen ikon PWA → perbaiki izin folder → reload Apache.
Aman diulang.

Cek: baris terakhir `Selesai. Versi sekarang: <hash> "<pesan commit>"`.

> Claude Code di laptop juga bisa menjalankan perintah `ssh ... update.sh` itu
> untukmu setelah selesai memperbaiki kode.

**Kalau `git pull` di VPS ditolak** (`Your local changes ... would be overwritten`):
ada yang mengedit langsung di server. Kembalikan ke kondisi git:

```bash
cd /var/www/stok
git checkout -- . && git clean -fd
bash deploy/update.sh
```

---

## 5. Backup otomatis

**Lakukan sebelum aplikasi dipakai serius.** Di **SSH**:

```bash
sudo mkdir -p /var/backups/stok
sudo chown "$USER" /var/backups/stok
crontab -e
```

Kalau ditanya editor pilih **`1` (nano)**. Tambahkan baris ini di paling bawah:

```
15 2 * * *  /var/www/stok/deploy/backup.sh >> $HOME/stok-backup.log 2>&1
```

Simpan: `Ctrl+O` → Enter → `Ctrl+X`.

Setiap hari **jam 02:15** membuat `db.sql.gz` + `uploads.tgz` di
`/var/backups/stok/<tanggal_jam>/`, menyimpan **14 backup terakhir**.

Uji sekarang tanpa menunggu jadwal:

```bash
bash /var/www/stok/deploy/backup.sh
ls -lh /var/backups/stok/
```

⚠️ **Backup di VPS yang sama bukan backup sungguhan** (hilang kalau VPS-nya hilang).
Tarik ke laptop berkala:

```powershell
scp -i $env:USERPROFILE\.ssh\id_ed25519 -r ubuntu@IP_VPS:/var/backups/stok C:\backup-stok\
```

### Pembersih file lama (cron mingguan)

Menu **Pengaturan Sistem → Backup Database** menyimpan file `.sql` di
`storage/backups/` dan **tidak pernah menghapusnya sendiri** — lama-lama
memakan kuota disk hosting. Skrip `deploy/cleanup.sh` membereskannya
(plus arsip log lama & sisa file sementara Dompdf).

Tambahkan di `crontab -e`, di bawah baris `backup.sh`:

```
30 3 * * 0  /var/www/stok/deploy/cleanup.sh >> $HOME/stok-cleanup.log 2>&1
```

Setiap **Minggu jam 03:30**: hapus backup DB > 30 hari **tapi selalu
sisakan 7 yang terbaru**, hapus arsip `logs/error.log.*` > 30 hari, dan
buang file `dompdf_*` nyangkut di folder temp.

Uji dulu tanpa menghapus apa pun:

```bash
DRY_RUN=1 bash /var/www/stok/deploy/cleanup.sh
```

Ubah ambang lewat env var, mis. simpan lebih lama:
`DAYS=60 KEEP=10 bash /var/www/stok/deploy/cleanup.sh`.

---

## 6. Pindah dari VPS gratis ke VPS berbayar

Setelah yakin semua jalan & sudah sewa VPS berbayar (Ubuntu 22.04/24.04).
Downtime nyata biasanya **< 30 menit**.

### 6.1 Persiapan (H-1 hari)

- Panel DNS: pastikan A record `stok` (atau `stok-test`) **TTL = 300**.
- VPS berbayar aktif, catat **`IP_BARU`**, buka port **22 / 80 / 443**.

### 6.2 Siapkan VPS baru

SSH ke **VPS baru**, buka firewall OS (kalau providernya memblokir — Oracle iya,
kebanyakan VPS berbayar tidak), lalu:

```bash
sudo apt-get update -y && sudo apt-get install -y git
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
```

- Subdomain: isi yang **final** → `stok.hexamultienergi.com`.
- Path dump: **Enter (kosong)** — DB diisi di langkah berikut.

### 6.3 Pindahkan data (dijalankan di **VPS LAMA**)

Skrip butuh bisa SSH dari VPS lama ke VPS baru. Siapkan dulu, di **VPS LAMA**:

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519   # kalau belum ada
cat ~/.ssh/id_ed25519.pub
```

Copy hasilnya, lalu di **VPS BARU** tambahkan ke `~/.ssh/authorized_keys`:

```bash
echo "ssh-ed25519 AAAA...isi-dari-vps-lama..." >> ~/.ssh/authorized_keys
```

Sekarang di **VPS LAMA**:

```bash
bash /var/www/stok/deploy/migrate-server.sh ubuntu@IP_BARU
```

Skrip: dump DB terbaru + arsip `public/uploads/` → kirim ke VPS baru →
restore + `php bin/migrate.php` di sana. Cek: muncul `OK. Versi: <hash>`.

### 6.4 HTTPS di VPS baru

```bash
sudo certbot --apache -d stok.hexamultienergi.com
```

(Kalau `ServerName` di VirtualHost belum `stok.hexamultienergi.com`, edit dulu
`/etc/apache2/sites-available/stok.conf` → ganti baris `ServerName` → `sudo systemctl reload apache2`.)

### 6.5 Verifikasi VPS baru **SEBELUM** ganti DNS

Di laptop, buka **Notepad sebagai Administrator** →
`C:\Windows\System32\drivers\etc\hosts` → tambah baris:

```
IP_BARU   stok.hexamultienergi.com
```

Simpan. Buka `https://stok.hexamultienergi.com` → jalankan **checklist E2**.
Kalau semua OK → **hapus lagi baris `hosts`** tadi.

### 6.6 Alihkan trafik

Panel DNS: ubah A record `stok` → **`IP_BARU`**. Karena TTL 300, dalam ± 5 menit
semua pengguna pindah.

### 6.7 Bersih-bersih

- Pantau 1–2 hari. Aman → matikan / hapus VPS gratis.
- Naikkan TTL A record ke `3600`.
- Update cron backup & tujuan `scp` ke IP baru.

---

## 7. Peta folder & perintah penting di VPS

```
/var/www/stok/                 ← aplikasi (hasil git clone)
├── config/local.php           ← kredensial DB server ini (TIDAK di git)
├── public/                    ← DocumentRoot Apache
│   ├── uploads/               ← foto/file upload (di-backup, tidak di git)
│   ├── manifest.webmanifest   ← PWA
│   └── sw.js                  ← PWA service worker
├── logs/error.log             ← log error aplikasi
├── storage/backups/           ← backup dari menu Pengaturan Sistem
├── bin/migrate.php            ← runner migrasi DB
├── bin/reset_user_password.php ← reset password user (darurat)
├── bin/cleanup.php            ← pembersih backup/log/temp lama
└── deploy/                    ← setup.sh / update.sh / backup.sh / cleanup.sh / migrate-server.sh

/etc/apache2/sites-available/stok.conf   ← konfigurasi VirtualHost
/var/log/apache2/stok_error.log          ← log Apache
/var/backups/stok/                       ← backup terjadwal (cron)
```

Perintah yang sering dipakai (di SSH):

```bash
# status layanan
sudo systemctl status apache2
sudo systemctl status mariadb

# lihat log error real-time
sudo tail -f /var/log/apache2/stok_error.log /var/www/stok/logs/error.log

# migrasi database
cd /var/www/stok
php bin/migrate.php --status      # lihat mana yang belum jalan
php bin/migrate.php               # jalankan yang pending

# reload Apache setelah ubah config
sudo systemctl reload apache2

# masuk MySQL
sudo mysql db_stok_proyek
```

Ringkasan seluruh perintah deploy:

```bash
# --- setup pertama (VPS baru) ---
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
sudo certbot --apache -d SUBDOMAIN
php /var/www/stok/bin/reset_user_password.php admin --yes

# --- update rutin (setelah git push) ---
ssh ubuntu@IP_VPS "bash /var/www/stok/deploy/update.sh"

# --- backup manual ---
bash /var/www/stok/deploy/backup.sh

# --- pindah VPS (di VPS LAMA) ---
bash /var/www/stok/deploy/migrate-server.sh ubuntu@IP_BARU
```

---

## 8. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| SSH `Connection timed out` | IP salah, atau Security List port 22 ditutup (harusnya default terbuka). |
| SSH `Permission denied (publickey)` | Kunci salah / belum ditempel saat buat VM. Pakai `-i` ke file kunci yang benar; user harus `ubuntu`. |
| `http://IP_VPS` tidak kebuka padahal Apache `active (running)` | **Firewall OS** belum dibuka (C1) **dan/atau** Security List (B3-a). Jalankan 3 perintah `iptables` di C1. |
| `certbot` gagal: *Timeout during connect* / *NXDOMAIN* | DNS belum propagasi (cek `nslookup`), atau Cloudflare masih "Proxied" (set "DNS only"), atau port 80 masih tertutup. |
| Halaman putih / **HTTP 500** | `sudo tail -f /var/log/apache2/stok_error.log` dan `/var/www/stok/logs/error.log`. Paling sering: `config/local.php` salah kredensial, atau `composer install` gagal → `cd /var/www/stok && composer install --no-dev`. |
| CSS/JS tidak muncul, sub-halaman semua **404** | `mod_rewrite` belum aktif → `sudo a2enmod rewrite && sudo systemctl reload apache2`. Atau `AllowOverride All` belum ada di VirtualHost (`stok.conf`). |
| Upload foto gagal / foto tidak tampil | Izin folder: `sudo chown -R www-data:www-data /var/www/stok/public/uploads /var/www/stok/storage /var/www/stok/logs` lalu `sudo chmod -R 775` folder-folder itu. |
| Export **PDF / Excel** error | `composer install --no-dev` belum sukses → `bash /var/www/stok/deploy/update.sh`. |
| Menu **Backup** (Pengaturan Sistem) gagal | `mysqldump` tidak ada di PATH → di `config/local.php` set `'mysqldump_path' => 'mysqldump'` (biasanya sudah otomatis). |
| Login **kena kunci** terus | Normal setelah 5 gagal/akun atau 8 gagal/IP dalam 15 menit. Tunggu hitung mundur, atau reset: `php /var/www/stok/bin/reset_user_password.php <username> --yes`. |
| **PWA** tidak bisa di-install di HP | Belum HTTPS, atau `manifest.webmanifest` / `sw.js` balas 404 → cek `curl -I https://SUBDOMAIN/sw.js` (harus `200`). |
| Sudah `update.sh` tapi HP masih tampilan lama | Cache service worker. Di laptop: naikkan angka `VERSION` di `public/sw.js`, commit, push, `update.sh`, lalu di HP tutup-buka app 2×. |
| `git pull` di VPS ditolak | Ada edit langsung di server → `cd /var/www/stok && git checkout -- . && git clean -fd && bash deploy/update.sh`. |
| Migrasi DB error | `php bin/migrate.php --status` untuk lihat detail; perbaiki penyebabnya lalu `php bin/migrate.php`. |

