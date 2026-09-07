# Panduan Deploy — Sistem Kontrol Stok Proyek

Panduan **dari nol sampai jadi**: aplikasi ini online di `stok.hexamultienergi.com`,
pakai HTTPS, bisa di-*install* sebagai app di HP (PWA), dan kamu tetap bisa
mengembangkannya di laptop seperti sekarang.

Rencana:

1. **Bagian A–E** → pasang di **VPS gratis** (Oracle Cloud) untuk uji coba.
2. **Bagian F–G** → cara update harian & backup.
3. **Bagian H** → kalau sudah oke, pindah ke **VPS berbayar** (± 20 menit).

> Istilah singkat: **VPS** = komputer server sewaan di internet. **SSH** = cara
> masuk ke VPS lewat terminal. **DNS / A record** = "buku alamat" yang
> menghubungkan `stok.domain.com` ke IP VPS. **Subdomain** = `stok.` di depan
> domain kamu.

---

## Prinsip penting (biar tidak bingung nanti)

Aplikasi ini isinya cuma 3 bagian:

| Bagian | Disimpan di | Cara pindah/backup |
|---|---|---|
| **Kode program** | GitHub (`github.com/adedian/Stok-proyek`, repo publik) | `git clone` / `git pull` |
| **Konfigurasi server** | 1 file `config/local.php` (tidak masuk git) | dibuat otomatis oleh `deploy/setup.sh` |
| **Data** | Database MySQL + folder `public/uploads/` | 1 file `.sql` + 1 file `.tgz` |

Karena itu: pindah VPS = jalankan `deploy/setup.sh` di VPS baru + salin data + ganti DNS.

**Kamu tetap ngoding di laptop (XAMPP + Claude Code).** VPS cuma "menerima" hasilnya:
`push ke GitHub` → di VPS jalankan `deploy/update.sh`. Aplikasi di VPS **jangan**
diedit langsung.

---

# BAGIAN A — Persiapan di laptop (Windows)

### A1. Yang harus sudah ada
- Aplikasi jalan normal di XAMPP (`http://localhost/stok-proyek/public/`). ✅
- Punya akun **GitHub** (`adedian`) & repo sudah ada. ✅
- Punya **domain** `hexamultienergi.com` + bisa masuk ke panel DNS-nya.
- Windows 10/11 (sudah ada `ssh` & `scp` bawaan — dipakai lewat **PowerShell** / Windows Terminal).
- Kartu debit/kredit untuk verifikasi identitas Oracle (Always Free **tidak ditagih**).

### A2. Pastikan kode terbaru sudah di GitHub
Buka PowerShell:

```powershell
cd C:\xampp\htdocs\stok-proyek
git status
git add -A
git commit -m "siap deploy"
git push origin master
```

Kalau `git status` bilang "nothing to commit, working tree clean" dan `git push`
bilang "Everything up-to-date", berarti sudah beres.

### A3. Buat dump (salinan) database lokal
Masih di PowerShell:

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --databases db_stok_proyek --add-drop-database --result-file=C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql
```

> Pakai `--result-file=` (bukan `>`), supaya file tidak rusak gara-gara encoding PowerShell.

Hasilnya: `C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql`. File ini sudah berisi
skema + data + riwayat migrasi. **Jangan** di-commit ke git (sudah ter-*ignore*).

> Kalau nanti mau produksi **mulai bersih tanpa data uji**, lewati langkah ini —
> lihat catatan di `deploy/setup.sh`.

### A4. Buat kunci SSH (sekali seumur hidup)
```powershell
ssh-keygen -t ed25519 -C "deploy-stok"
```

Tekan **Enter** 3× (lokasi default `C:\Users\NAMA\.ssh\id_ed25519`, tanpa passphrase
biar gampang — atau isi passphrase kalau mau lebih aman).

Lihat isi kunci **publik** (ini yang nanti ditempel ke Oracle):

```powershell
Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
```

Copy seluruh barisnya (mulai `ssh-ed25519 ...`).

---

# BAGIAN B — Buat VPS gratis (Oracle Cloud Always Free)

> Alternatif kalau Oracle susah: **AWS EC2 / Lightsail free tier** (gratis 12 bulan).
> Langkah C–H sama saja, hanya cara buat VM & buka port yang beda.

### B1. Daftar akun
1. Buka **https://www.oracle.com/cloud/free/** → **Start for free**.
2. Isi email, negara **Indonesia**, verifikasi HP (SMS), verifikasi kartu
   (ada penahanan ± Rp 1 lalu dikembalikan — bukan tagihan).
3. Pilih **Home Region** yang dekat, mis. **Singapore** atau **Jakarta** (kalau ada).
   ⚠️ Region tidak bisa diganti setelahnya.
4. Tunggu email "Your account is ready" (kadang beberapa menit–jam).

### B2. Buat instance (VM) Ubuntu
1. Login ke **cloud.oracle.com** → menu ☰ → **Compute → Instances → Create instance**.
2. **Name**: `stok-vps`.
3. **Image and shape** → **Edit**:
   - **Image**: **Canonical Ubuntu** → versi **22.04** (atau 24.04).
   - **Shape**: klik **Change shape** →
     - Pilihan terbaik: **Ampere** → `VM.Standard.A1.Flex` → set **1 OCPU / 6 GB**
       (Always Free: total sampai 4 OCPU / 24 GB — cukup pakai 1/6 dulu).
     - Kalau "out of capacity", pakai **AMD** → `VM.Standard.E2.1.Micro` (Always Free, lebih kecil tapi cukup).
4. **Networking**: biarkan default (buat VCN baru). Pastikan **Assign a public IPv4 address = Yes**.
5. **Add SSH keys** → **Paste public keys** → tempel isi `id_ed25519.pub` dari langkah A4.
6. **Create**. Tunggu status **RUNNING**.
7. Catat **Public IP address** (mis. `140.238.xx.xx`) — panggil `IP_VPS` di panduan ini.

### B3. Buka port 80 & 443 (WAJIB — 2 tempat)

**(a) Di Oracle Console — Security List:**
1. Halaman instance → klik nama **Virtual cloud network** → **Security Lists** →
   **Default Security List**.
2. **Add Ingress Rules** → tambah 2 aturan:
   | Source CIDR | IP Protocol | Destination Port |
   |---|---|---|
   | `0.0.0.0/0` | TCP | `80` |
   | `0.0.0.0/0` | TCP | `443` |
3. **Add Ingress Rules** (simpan).

**(b) Di dalam VPS — firewall OS** (Oracle Ubuntu memblokir semua kecuali 22 secara default).
Nanti setelah bisa SSH (Bagian C1), jalankan:

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

> Kalau lupa langkah (b), gejalanya: `certbot` gagal & subdomain tidak kebuka
> padahal Apache jalan.

---

# BAGIAN C — Deploy aplikasi ke VPS

### C1. Masuk ke VPS lewat SSH (dari PowerShell)
```powershell
ssh -i $env:USERPROFILE\.ssh\id_ed25519 ubuntu@IP_VPS
```

- Ganti `IP_VPS` dengan IP dari B2.
- Pertama kali akan tanya `Are you sure you want to continue connecting` → ketik **yes**.
- User default Oracle Ubuntu = **`ubuntu`**.
- Kalau berhasil, prompt berubah jadi `ubuntu@stok-vps:~$`.

Sekalian buka port firewall OS (Bagian B3-b) sekarang.

### C2. Upload dump database ke VPS
Buka **PowerShell baru** (jangan tutup yang SSH):

```powershell
scp -i $env:USERPROFILE\.ssh\id_ed25519 C:\xampp\htdocs\stok-proyek\db_stok_proyek.sql ubuntu@IP_VPS:/tmp/
```

### C3. Jalankan skrip setup
Kembali ke jendela **SSH**:

```bash
sudo apt-get update -y && sudo apt-get install -y git
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
```

Skrip akan tanya 3 hal:

| Pertanyaan | Isi dengan |
|---|---|
| `Subdomain (mis. stok-test.hexamultienergi.com):` | `stok-test.hexamultienergi.com` |
| `Password untuk user DB 'stok':` | password kuat, **catat**. Pakai huruf/angka + simbol aman (`- _ . ! @ # %`); **hindari** kutip `'` `"` dan backslash `\` (bisa merusak skrip) |
| `Path file dump .sql...:` | `/tmp/db_stok_proyek.sql` |

Lalu skrip otomatis (± 3–8 menit):
- pasang Apache + MariaDB + PHP 8 + ekstensi + Composer
- buat database `db_stok_proyek` + user `stok`
- `git clone` aplikasi ke `/var/www/stok` + `composer install --no-dev`
- tulis `config/local.php` (`app_env = production`)
- restore dump + jalankan migrasi
- generate ikon PWA
- set izin folder upload/log/backup
- buat VirtualHost Apache dengan **DocumentRoot `/var/www/stok/public`**

Di akhir muncul pesan "SELESAI (HTTP)".

**Tes cepat**: buka browser ke `http://IP_VPS` — halaman login harus muncul
(CSS mungkin belum sempurna karena diakses lewat IP; itu normal, nanti benar
setelah pakai subdomain).

---

# BAGIAN D — Subdomain + HTTPS

### D1. Arahkan subdomain ke VPS (di panel DNS domain)
Masuk ke tempat kamu kelola DNS `hexamultienergi.com` (Cloudflare / cPanel /
registrar). Tambah record:

| Type | Name / Host | Value / Points to | TTL |
|---|---|---|---|
| `A` | `stok-test` | `IP_VPS` | `300` (5 menit) |

> Kalau pakai **Cloudflare**: set kolom **Proxy status = DNS only** (awan abu-abu)
> dulu supaya `certbot` bisa verifikasi. Bisa dinyalakan lagi nanti.

Tunggu propagasi (biasanya 1–15 menit). Cek dari PowerShell:

```powershell
nslookup stok-test.hexamultienergi.com
```

Sampai muncul `IP_VPS` di hasilnya.

### D2. Pasang SSL (HTTPS gratis, Let's Encrypt)
Di jendela **SSH**:

```bash
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d stok-test.hexamultienergi.com
```

- Isi email (untuk notifikasi kedaluwarsa).
- Setuju TOS (`Y`).
- Kalau ditanya redirect: pilih **2 (Redirect)** — semua `http` dialihkan ke `https`.

Perpanjangan otomatis sudah aktif (`systemctl status certbot.timer`).

Buka **https://stok-test.hexamultienergi.com** → halaman login rapi + gembok HTTPS. 🎉

---

# BAGIAN E — Finalisasi & pengujian

### E1. Amankan akun bawaan
Ada 2 akun Super Admin: `ade` (punyamu) dan `admin` (default, password lemah).
Di SSH:

```bash
php /var/www/stok/bin/reset_user_password.php admin --yes
```

Ini mencetak password acak baru **sekali** — catat, atau abaikan & nonaktifkan
akun `admin` lewat menu **User Management** (login pakai `ade`).

### E2. Checklist pengujian (buka situsnya, coba satu-satu)
- [ ] Login pakai `ade` berhasil.
- [ ] Buka tiap menu: PO, Pembayaran, Kas, Penerimaan, Pengeluaran, Stok, Laporan, Master Data, dst — tidak ada error.
- [ ] **Upload foto** (Penerimaan Barang → Foto, atau Pengaturan Akun → Foto Profil) → tersimpan & tampil.
- [ ] **Laporan → Export Excel** dan **Export PDF** → file ter-download & kebuka.
- [ ] **Pengaturan Sistem → Backup** → file backup terbuat.
- [ ] Buka dari **HP** (browser): tidak ada geser horizontal, tabel jadi kartu, tombol enak dipencet.
- [ ] Di SSH: `tail -n 50 /var/www/stok/logs/error.log` → kosong / tidak ada error baru.

### E3. Install sebagai app di HP (PWA)
Syaratnya HTTPS sudah aktif (Bagian D2).

- **Android (Chrome)**: buka `https://stok-test.hexamultienergi.com` → login →
  menu **⋮** → **Add to Home screen / Install app**. (Atau muncul banner "Install".)
- **iPhone (Safari)**: buka situsnya → tombol **Share** (kotak+panah) → **Add to Home Screen**.

Setelah itu ada ikon "Stok Proyek" di layar HP, dibuka jalan **full-screen** seperti
aplikasi biasa, ada halaman "Tidak ada koneksi" kalau internet putus.

**Cek teknis PWA** (opsional, di Chrome laptop): buka situs → tekan `F12` →
tab **Application** → **Manifest** (harus tampil ikon & nama) & **Service Workers**
(status *activated and running*). Atau tab **Lighthouse** → centang **PWA** → *Analyze*.

---

# BAGIAN F — Kerja sehari-hari: mengubah aplikasi

Alurnya selalu sama:

```
[Laptop] edit + Claude Code  →  git commit  →  git push
[VPS]    bash deploy/update.sh
```

**Di laptop** (PowerShell):

```powershell
cd C:\xampp\htdocs\stok-proyek
git add -A
git commit -m "penjelasan perubahan"
git push origin master
```

**Di VPS** (SSH), atau langsung dari laptop tanpa login:

```powershell
ssh -i $env:USERPROFILE\.ssh\id_ed25519 ubuntu@IP_VPS "bash /var/www/stok/deploy/update.sh"
```

`update.sh` menjalankan: `git pull` → `composer install` → `php bin/migrate.php`
→ perbaiki izin folder → reload Apache. Aman diulang.

> Claude Code di laptop juga bisa menjalankan perintah `ssh ... update.sh` itu
> untukmu setelah selesai memperbaiki kode.

**Kalau `git pull` di VPS ditolak** ("local changes"): berarti ada yang mengedit
langsung di server. Kembalikan:

```bash
cd /var/www/stok && git checkout -- . && git clean -fd && bash deploy/update.sh
```

---

# BAGIAN G — Backup otomatis (lakukan sebelum dipakai serius)

Di SSH:

```bash
sudo mkdir -p /var/backups/stok
sudo chown "$USER" /var/backups/stok
crontab -e
```

(Kalau ditanya editor, pilih `nano` = nomor 1.) Tambahkan baris ini di paling bawah,
lalu simpan (`Ctrl+O`, `Enter`, `Ctrl+X`):

```
15 2 * * *  /var/www/stok/deploy/backup.sh >> $HOME/stok-backup.log 2>&1
```

Setiap hari jam 02:15 membuat `db.sql.gz` + `uploads.tgz` di `/var/backups/stok/`,
menyimpan 14 backup terakhir. Cek hasilnya: `cat ~/stok-backup.log`.

⚠️ **Backup di VPS yang sama bukan backup sungguhan.** Salin folder itu ke tempat
lain secara berkala, misalnya tarik ke laptop:

```powershell
scp -i $env:USERPROFILE\.ssh\id_ed25519 -r ubuntu@IP_VPS:/var/backups/stok C:\backup-stok\
```

(atau setup `rclone` ke Google Drive di VPS — di luar cakupan panduan ini).

---

# BAGIAN H — Pindah dari VPS gratis ke VPS berbayar

Setelah yakin semua jalan, dan kamu sudah sewa VPS berbayar.

### H1. Persiapan (H-1 hari)
- Di panel DNS, pastikan A record subdomain **TTL = 300** (sudah dari D1).
- Beli VPS berbayar (Ubuntu 22.04/24.04), catat `IP_BARU`, buka port 22/80/443.

### H2. Siapkan VPS baru (sama seperti Bagian B3-b + C1 + C3)
1. SSH ke VPS baru, buka firewall port 80/443 kalau perlu.
2. Jalankan `setup.sh` **tanpa dump** (biarkan pertanyaan path dump kosong):
   ```bash
   sudo apt-get update -y && sudo apt-get install -y git
   git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
   bash /tmp/skp/deploy/setup.sh
   ```
   Isi subdomain **final**: `stok.hexamultienergi.com`. Catat password DB.
   DB masih kosong — akan diisi di langkah berikut.

### H3. Pindahkan data (dijalankan di VPS LAMA)
```bash
bash /var/www/stok/deploy/migrate-server.sh ubuntu@IP_BARU
```

Skrip: dump DB terbaru + arsip `uploads/` dari VPS lama → kirim ke VPS baru →
restore + `php bin/migrate.php` di sana.

> Butuh SSH dari VPS lama ke VPS baru. Cara termudah: di VPS lama jalankan
> `ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519`, lalu `cat ~/.ssh/id_ed25519.pub`
> dan tambahkan ke `~/.ssh/authorized_keys` di VPS baru.

### H4. HTTPS di VPS baru
```bash
sudo certbot --apache -d stok.hexamultienergi.com
```

### H5. Verifikasi VPS baru SEBELUM ganti DNS
Di laptop, edit sementara `C:\Windows\System32\drivers\etc\hosts` (buka Notepad
sebagai Administrator), tambah baris:

```
IP_BARU   stok.hexamultienergi.com
```

Buka `https://stok.hexamultienergi.com` → jalankan checklist Bagian E2. Kalau
semua oke, **hapus lagi baris di file hosts** itu.

### H6. Alihkan trafik
Di panel DNS, ubah A record `stok` → **`IP_BARU`**. Karena TTL 300, dalam ± 5 menit
semua pengguna pindah ke VPS baru.

### H7. Bersih-bersih
- Pantau 1–2 hari. Kalau aman: matikan/hapus VPS gratis.
- Naikkan TTL A record kembali ke `3600`.
- Update backup cron & tujuan `scp` ke IP baru.

Total downtime nyata: dari `migrate-server.sh` sampai DNS pindah, biasanya
**< 30 menit**.

---

# BAGIAN I — Kalau ada masalah

| Gejala | Penyebab & solusi |
|---|---|
| SSH: `Connection timed out` | Security List Oracle belum buka port 22 (harusnya default), atau IP salah. |
| Situs tidak kebuka padahal Apache `active` | Port 80/443 belum dibuka di **firewall OS** (Bagian B3-b) **dan/atau** Security List. |
| `certbot` gagal (`Timeout` / `NXDOMAIN`) | DNS belum propagasi, atau Cloudflare masih "Proxied" (set ke "DNS only"), atau port 80 belum terbuka. |
| Halaman putih / Error 500 | `sudo tail -f /var/log/apache2/stok_error.log` dan `/var/www/stok/logs/error.log`. Sering: `config/local.php` salah, atau `composer install` belum jalan. |
| CSS/JS tidak muncul, semua sub-halaman 404 | `mod_rewrite` belum aktif (`sudo a2enmod rewrite && sudo systemctl reload apache2`) atau `AllowOverride All` belum ada di VirtualHost. |
| Upload foto gagal / foto tidak tampil | Izin folder: `sudo chown -R www-data:www-data /var/www/stok/public/uploads` lalu `sudo chmod -R 775` folder itu. |
| Export PDF/Excel error | `composer install --no-dev` belum sukses → jalankan ulang `bash deploy/update.sh`. |
| Backup manual (menu Pengaturan) gagal | `mysqldump` tidak ada di PATH → di `config/local.php` set `'mysqldump_path' => 'mysqldump'` (biasanya sudah). |
| PWA tidak bisa di-install di HP | Belum HTTPS, atau `manifest.webmanifest` / `sw.js` balas 404 (cek `curl -I https://.../sw.js`). |
| Sudah update tapi HP masih versi lama | Service worker cache. Naikkan angka `VERSION` di `public/sw.js`, commit, `deploy/update.sh`, lalu buka app 2×. |
| Migrasi DB nyangkut | `php bin/migrate.php --status` untuk lihat, `php bin/migrate.php` untuk jalankan yang pending. |

**Lihat log real-time saat ada masalah:**
```bash
sudo tail -f /var/log/apache2/stok_error.log /var/www/stok/logs/error.log
```

---

# Lampiran — daftar perintah singkat

```bash
# Setup pertama (di VPS baru)
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
sudo certbot --apache -d SUBDOMAIN
php /var/www/stok/bin/reset_user_password.php admin --yes

# Update rutin (setelah git push dari laptop)
bash /var/www/stok/deploy/update.sh
# atau dari laptop:
ssh ubuntu@IP_VPS "bash /var/www/stok/deploy/update.sh"

# Backup manual sekali
bash /var/www/stok/deploy/backup.sh

# Pindah VPS lama -> baru (dijalankan di VPS LAMA)
bash /var/www/stok/deploy/migrate-server.sh ubuntu@IP_BARU
```

File pendukung ada di folder [`deploy/`](deploy/):
`setup.sh`, `update.sh`, `backup.sh`, `migrate-server.sh`.
