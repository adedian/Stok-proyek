# HEXA STOK — Panduan Penggunaan Aplikasi

Panduan lengkap cara memakai **HEXA STOK**, aplikasi internal PT. Hexa Multi
Energi untuk kontrol Purchase Order, stok proyek, Kas, dan penagihan
(Invoice/Surat Jalan/Tanda Terima).

> Aplikasi bisa dipasang di HP seperti aplikasi biasa (PWA) — buka lewat
> Chrome/Safari di HP, lalu pilih **"Tambahkan ke Layar Utama" / "Add to Home
> Screen"**. Ikonnya akan muncul di HP tanpa perlu install dari Play
> Store/App Store.

---

## Daftar isi

- [1. Login & lupa password](#1-login--lupa-password)
- [2. Peran (Role) — siapa boleh apa](#2-peran-role--siapa-boleh-apa)
- [3. Dashboard](#3-dashboard)
- [4. Purchase Order](#4-purchase-order)
- [5. Pembayaran](#5-pembayaran)
- [6. Kas](#6-kas)
- [7. Validasi Kas](#7-validasi-kas)
- [8. Penerimaan Barang](#8-penerimaan-barang)
- [9. Validasi Barang](#9-validasi-barang)
- [10. Pengeluaran Barang & Surat Jalan](#10-pengeluaran-barang--surat-jalan)
- [11. Stok & Opname](#11-stok--opname)
- [12. Invoice Keluar](#12-invoice-keluar)
- [13. Tanda Terima](#13-tanda-terima)
- [14. Pembelian Offline](#14-pembelian-offline)
- [15. Laporan](#15-laporan)
- [16. User Management](#16-user-management)
- [17. Master Data](#17-master-data)
- [18. Tempat Sampah](#18-tempat-sampah)
- [19. Pengaturan Sistem](#19-pengaturan-sistem)
- [20. Fitur yang sama di semua modul](#20-fitur-yang-sama-di-semua-modul)
- [21. Istilah-istilah penting](#21-istilah-istilah-penting)
- [22. Pertanyaan umum](#22-pertanyaan-umum)

---

## 1. Login & lupa password

1. Buka alamat aplikasi (dari HP atau komputer) → isi **Username** & **Password** → **Login**.
2. Setelah masuk, foto profil & nama muncul di pojok kanan atas. Klik untuk
   membuka **Pengaturan Akun**: ganti password sendiri, ganti foto profil,
   dan (untuk role tertentu) atur Tanda Tangan pribadi yang dipakai otomatis
   di cetakan Purchase Order.
3. **Lupa password?** Hubungi Super Admin — hanya Super Admin yang bisa
   me-reset password akun lain lewat menu **User Management**.
4. Login yang gagal berkali-kali akan **terkunci sementara** (proteksi
   keamanan) — tunggu beberapa menit lalu coba lagi.

---

## 2. Peran (Role) — siapa boleh apa

Setiap akun punya **1 role** yang menentukan menu apa saja yang muncul dan
apa yang boleh dilakukan. Enam role yang ada:

| Role | Fokus pekerjaan |
|---|---|
| **Super Admin** | Akses penuh ke semua modul, termasuk yang "terkunci" (User Management, Master Data, Tempat Sampah, Pengaturan Sistem, Tutup Bulan) |
| **Purchase** | Pembelian: Purchase Order, Kas (+ Kas Purchase & Kas project yang diberi akses), Validasi Barang, Invoice Keluar, Tanda Terima |
| **Accounting** | Keuangan: Kas & Bank (lihat semua), Validasi Kas, Pembayaran, Laporan, Master Rekening/Bank |
| **PIC Project** | Operasional lapangan per-project: Penerimaan Barang, Pengeluaran Barang, Validasi Barang, Kas project yang diberi akses |
| **Admin Project** | Sama seperti PIC Project (admin di sisi project), tapi izin-izin lain bisa berbeda sesuai pengaturan |
| **Project Manager** | Lihat-saja (view only) untuk sebagian besar modul, termasuk Kas divisi project — tidak bisa membuat/mengubah transaksi |

Menu yang tidak muncul di sidebar Anda memang **tidak diberikan** untuk role
Anda — ini normal, bukan error. Kalau ada yang terasa kurang/salah untuk
pekerjaan Anda, hubungi Super Admin (bisa disesuaikan lewat **Pengaturan
Sistem → Hak Akses**, lihat [Bagian 19](#19-pengaturan-sistem)).

---

## 3. Dashboard

Halaman pertama setelah login. Isinya ringkasan harian, hanya menampilkan
kartu yang relevan dengan role Anda:

- **Peringatan & Informasi Penting** — kotak berwarna untuk hal yang perlu
  ditindaklanjuti: Selisih Barang, Validasi Barang, Validasi Kas, Invoice
  Belum Tertagih, Stok Minimum. Klik tombolnya untuk langsung ke halaman
  terkait.
- **Kartu angka** — Total PO, Barang Menunggu Datang, Barang Diterima/Keluar
  Hari Ini, Stok Tersedia (per satuan), Invoice Belum Tertagih, Sisa Tagihan
  PO, dll.
- **Grafik Aktivitas Stok** — bisa difilter 7 Hari / 30 Hari / Bulan Ini /
  Tahun Ini.
- **Aktivitas Terbaru** — log singkat siapa melakukan apa (login, membuat
  transaksi, dll).
- Ada **lonceng notifikasi** di pojok kanan atas — dipakai untuk push
  notification (misalnya "Validasi Kas Menunggu", "Invoice Belum
  Tertagih").

---

## 4. Purchase Order

**Untuk apa:** membuat pesanan pembelian resmi ke Supplier.

**Siapa boleh:** lihat/buat/ubah — Super Admin, Purchase, Accounting. Hapus
— Super Admin & Purchase. (Project Manager tidak punya akses ke modul ini.)

**Langkah membuat PO:**
1. **Purchase Order → Tambah PO.**
2. Isi **Supplier**, **Project**, **Lokasi Pengiriman**, **Tanggal PO**,
   **Status** (draft / menunggu persetujuan), catatan, dan nomor/tanggal
   penawaran (quote) kalau ada.
3. Isi baris barang: pilih dari **Master Barang** atau ketik bebas, lalu
   Kategori, Satuan, Qty, Harga.
4. Nomor PO otomatis dibuat sistem saat disimpan (format
   `001/PO.HME/IX/2026` — lihat [Bagian 21](#21-istilah-istilah-penting)).
   Saat form masih dibuka, yang tampil hanya **pratinjau nomor** — nomor
   resmi baru "dibakar" setelah tombol Simpan ditekan.
5. Tanda tangan pada cetakan PO **otomatis** memakai Tanda Tangan pribadi
   pembuatnya (diatur di Pengaturan Akun), tidak perlu pilih manual.
6. Kalau status disimpan sebagai "Menunggu Persetujuan", notifikasi push
   otomatis terkirim ke yang berwenang menyetujui.

**Penting:** begitu ada **Penerimaan Barang** yang mengacu ke PO ini, baris
barangnya **terkunci** (tidak bisa dihapus/diganti lagi) — hanya field
header (catatan, status, dll) yang masih bisa diubah. Ini menjaga data
Penerimaan tetap konsisten dengan PO aslinya.

---

## 5. Pembayaran

**Untuk apa:** mencatat pembayaran (termin) ke Supplier atas sebuah PO.

**Siapa boleh:** lihat/buat/ubah — Super Admin & Accounting. Hapus — Super
Admin saja.

**Langkah:**
1. **Pembayaran → Tambah Pembayaran.**
2. Pilih **PO** yang mau dibayar (PO berstatus draft/dibatalkan tidak akan
   muncul di pilihan).
3. Isi **Termin ke-**, **Sumber Dana** (Bank / Kas Kecil / Kas Project — kalau
   pilih Bank, muncul field tambahan Jenis Bank), **Nominal**, **Tanggal**,
   dan **Bukti Bayar** (upload foto/PDF).
4. Status PO (Belum Bayar / Sebagian / Lunas) **dihitung otomatis** sistem
   dari total yang sudah dibayar dibanding total PO — tidak perlu diisi
   manual, dan sistem menolak kalau nominal melebihi sisa tagihan.

Ada juga halaman **Rekap Status Pembayaran** untuk melihat ringkasan semua
PO beserta status bayarnya sekaligus.

---

## 6. Kas

**Untuk apa:** buku kas masuk/keluar — biaya operasional, material proyek,
inventory kantor, dll. Kalau kategorinya menyentuh stok (misalnya beli
barang tunai lalu langsung dipakai proyek), stok **langsung bertambah** saat
Kas disimpan (tanpa lewat Penerimaan/Validasi Barang).

**Siapa boleh lihat:** semua role. **Buat/ubah/hapus** — Super Admin,
Accounting, Purchase, PIC Project, Admin Project (Project Manager hanya
lihat, tidak bisa mengubah apa pun).

### 6.1 Gerbang keamanan tambahan (khusus Purchase / PIC Project / Admin Project)

Tiga role ini **wajib verifikasi ulang** setiap membuka Kas — login
aplikasi saja belum cukup. Super Admin, Accounting, dan Project Manager
**langsung masuk** tanpa gerbang ini.

1. Klik menu **Kas** → muncul halaman **"Verifikasi Kas"**.
2. Masukkan **password akun Anda sendiri** (password login yang sama,
   **bukan** password terpisah) → **Masuk Kas**.
3. Setelah lolos, Anda masuk ke halaman Kas. Sesi ini berlaku sampai Anda
   klik **"Keluar Kas"**, atau otomatis terkunci lagi kalau tidak aktif
   dalam waktu tertentu (login aplikasi Anda **tetap aktif**, hanya sesi
   Kas-nya yang perlu diverifikasi ulang).

### 6.2 Data apa yang terlihat, per role

- **Purchase**: melihat **"Kas Purchase"** (semua transaksi Kas divisi
  Purchase, di seluruh perusahaan) **ditambah** Kas dari project-project
  yang **diberikan akses** oleh Super Admin.
- **PIC Project / Admin Project**: **hanya** Kas dari project yang diberikan
  akses — tidak ada tambahan lain.
- Kalau belum diberi akses ke project apa pun: Purchase tetap lihat Kas
  Purchase-nya; PIC Project/Admin Project akan melihat daftar **kosong**
  sampai Super Admin memberi akses (lihat [Bagian 17.1](#171-project)).

Setelah masuk Kas, ada **filter "Project"** (centang, kosong = tampilkan
semua yang boleh Anda lihat) untuk mempersempit tampilan ke 1 project
tertentu — hanya menampilkan project yang memang sudah diberikan akses ke
akun Anda.

### 6.3 Langkah mencatat transaksi Kas

1. **Kas → Tambah Kas.**
2. Isi **Tanggal**, **PIC** (nama penanggung jawab kas — dari Master Data →
   PIC Kas; kalau belum ada, bisa "Tambah PIC Cepat" langsung dari form),
   **Mutasi** (Masuk/Keluar), **Project** (wajib untuk PIC Project/Admin
   Project — itu satu-satunya cara transaksi bisa mereka lihat lagi
   nantinya; opsional untuk Purchase, kosongkan untuk masuk "Kas Purchase"),
   **Rekening** (opsional).
3. Isi baris rincian: **Uraian**, **Kategori Kas**, **Qty**, **Harga
   Satuan**. Kalau kategori itu "menyentuh stok", akan muncul kolom
   tambahan **Barang** (wajib dipilih dari Master Barang) & **Satuan** — dan
   stok akan otomatis bertambah begitu disimpan.
4. **No. Bukti** dibuat otomatis sistem sesuai prefix PIC yang dipilih
   (mis. `AD-0001`), tidak perlu diisi manual.
5. Simpan → transaksi masuk daftar dengan status **"Menunggu"** validasi
   (lihat [Bagian 7](#7-validasi-kas)).

### 6.4 Kartu saldo & cetak

- **Kartu saldo** (kalau role Anda diizinkan lihat): Super Admin/Accounting
  melihat total + rincian per divisi; role lain hanya melihat saldo divisi
  sendiri.
- **Laporan Kas** (dari menu **Laporan**) bisa difilter Project/PIC/
  Rekening/tanggal, lalu **Cetak PDF**, **Export Excel**, atau **"Tarik
  Semua"** (rekap semua transaksi sesuai filter, dikelompokkan per PIC).
- **Cetak Terpilih** (centang beberapa transaksi → cetak voucher Bukti Kas
  Masuk/Keluar) — khusus Super Admin & Accounting.

---

## 7. Validasi Kas

**Untuk apa:** persetujuan atas transaksi Kas yang baru dibuat, sebelum
dianggap final.

**Siapa boleh:** Super Admin, Accounting, Purchase, Project Manager — tapi
**per-divisi**: Accounting hanya memvalidasi Kas divisi Accounting, Purchase
hanya divisi Purchase, Project Manager hanya divisi Project. Super Admin
bisa validasi semua divisi, termasuk "Umum" yang tidak punya validator role
lain.

**Langkah:**
1. **Validasi Kas** → daftar transaksi berstatus "Menunggu" muncul duluan.
2. Klik **Tinjau** pada satu transaksi → periksa detailnya → pilih
   **Setujui** atau **Tolak**.
3. Kalau **Tolak**, wajib isi **alasan/catatan** penolakan.
4. Transaksi yang **Tervalidasi** jadi terkunci (hanya Super Admin yang bisa
   edit/hapus lagi). Transaksi yang **Ditolak** bisa diedit ulang oleh
   pembuatnya — begitu diedit, statusnya otomatis balik jadi "Menunggu"
   untuk divalidasi ulang.

---

## 8. Penerimaan Barang

**Untuk apa:** mencatat barang yang benar-benar tiba secara fisik,
dicocokkan dengan PO / Pembelian Offline, atau langsung "dari Pemakai"
(serah-terima internal tanpa dokumen sumber).

**Siapa boleh:** lihat — semua role. Buat/ubah — Super Admin, Purchase,
Accounting, PIC Project, Admin Project. Hapus — Super Admin & Purchase.

**Langkah:**
1. **Penerimaan Barang → Tambah Penerimaan.**
2. Pilih sumber: **PO**, **Pembelian Offline**, atau **Dari Pemakai**.
3. Pilih dokumen sumbernya (kalau ada), isi **Project**, **Tanggal
   Terima**, **Nama Penerima**, upload **Foto Barang**, **Invoice** (opsional,
   PDF otomatis dikompres), dan foto **Surat Jalan** (boleh lebih dari satu).
4. Untuk setiap baris barang, isi **Qty Diterima** — sistem otomatis
   membandingkan dengan qty yang dipesan dan menandai status **Sesuai /
   Kurang / Lebih**. Barang yang datang tapi tidak ada di dokumen sumber
   masuk sebagai baris **"Barang Lain"** terpisah.
5. **Penting: stok BELUM bertambah di titik ini.** Stok baru masuk setelah
   tiap baris **divalidasi** (lihat [Bagian 9](#9-validasi-barang)).

Mengedit atau menghapus penerimaan yang stoknya sudah divalidasi akan
otomatis membalik (reverse) stok itu lebih dulu. Penerimaan bertipe "Dari
Pemakai" tidak bisa diedit — kalau salah, hapus lalu buat ulang.

---

## 9. Validasi Barang

**Untuk apa:** gerbang wajib sebelum barang dari Penerimaan benar-benar
menambah stok sistem.

**Siapa boleh lihat:** Super Admin, Purchase, Accounting, PIC Project,
Project Manager. **Yang bisa memvalidasi:** Super Admin, Purchase,
Accounting, PIC Project.

**Langkah:**
1. **Validasi Barang** → daftar barang yang belum divalidasi muncul.
2. Untuk tiap baris, tentukan **status perbandingan**: Sesuai / Kurang /
   Lebih / Barang Lain, dan isi **catatan** (wajib kecuali "Sesuai").
3. Simpan → kalau statusnya valid, **stok langsung bertambah otomatis**
   sesuai qty diterima. Kalau sebelumnya sudah tervalidasi lalu dibatalkan,
   stok dikurangi lagi (dibalik) secara otomatis — tidak perlu menghitung
   manual.

Ada juga sub-halaman **"Belum Sesuai"** (daftar selisih yang perlu
ditindaklanjuti) dan **"Sudah Sesuai"** (riwayat yang sudah beres).

---

## 10. Pengeluaran Barang & Surat Jalan

**Untuk apa:** mencatat barang keluar dari stok — ke sebuah **Project**, atau
ke **Client** (dikaitkan ke Invoice Keluar).

**Siapa boleh lihat:** semua role. Buat/ubah — Super Admin, Purchase,
Accounting, PIC Project, Admin Project. Hapus — Super Admin & Purchase.

**Langkah:**
1. **Pengeluaran Barang → Tambah Pengeluaran.**
2. Pilih **Tujuan**: Project, atau Client (Invoice) — opsi "Client
   (Invoice)" hanya muncul untuk Super Admin/Accounting/Purchase; role lain
   otomatis diarahkan ke tujuan Project.
3. Bisa pilih **lebih dari 1 barang sekaligus** dalam satu form — tiap
   barang jadi 1 baris pengeluaran, semuanya berbagi tujuan/PIC/tanggal yang
   sama.
4. Isi **Qty**, **PIC**, **Tanggal Keluar**, catatan. **Stok langsung
   berkurang** saat disimpan — kalau stok tidak cukup untuk **salah satu**
   barang, **seluruh** transaksi batal (tidak ada yang tersimpan separuh).

**Surat Jalan** (dokumen cetak untuk serah-terima fisik):
1. Dari daftar **Pengeluaran Barang**, centang beberapa baris yang belum
   terhubung Surat Jalan → **Buat Surat Jalan**.
2. Isi Tujuan, Kota Serah Terima, No. Kendaraan, Nama Sopir, Pengirim,
   Penerima, Tanda Tangan.
3. Surat Jalan **tidak membuat transaksi stok baru** — murni dokumen
   pembungkus dari baris Pengeluaran yang sudah ada. Menghapus Surat Jalan
   hanya melepas ikatannya (baris Pengeluaran tetap ada, bisa dikelompokkan
   ulang ke Surat Jalan lain).

---

## 11. Stok & Opname

**Untuk apa:** dua sub-halaman — **Stok Barang** (kartu stok realtime, siapa
saja dengan aksesnya bisa lihat riwayat keluar-masuk per barang) dan **Stok
Opname** (hitung fisik berkala untuk mencocokkan data sistem dengan barang
nyata di gudang).

**Siapa boleh:** lihat/buat Opname — Super Admin & Accounting. Role lain
(Purchase, PIC Project, dll) melihat stok lewat **Laporan → Stok Barang**
(tanpa harga untuk Purchase).

**Langkah membuat Stok Opname:**
1. **Stok & Opname → Tambah Opname.**
2. Pilih **Jenis Stok** (Stok Proyek / Stok Lampu / Inventory Kantor) dan
   **Project** (opsional). Qty sistem terisi otomatis dari data stok saat
   ini.
3. Isi **Qty Fisik** hasil hitung nyata untuk tiap barang, simpan sebagai
   **draft** dulu kalau belum yakin.
4. Klik **Selesaikan Opname** → selisih (fisik vs sistem) langsung
   diterapkan ke stok, status berubah jadi **Selesai** dan terkunci.
   Menghapus opname yang sudah selesai (khusus Super Admin) otomatis
   membalik penyesuaiannya.

---

## 12. Invoice Keluar

**Untuk apa:** tagihan (AR) dari HME ke Client. Ada 2 jenis: **Project**
(nomor `INV.HME`) dan **Lampu** (nomor `FKT.HME`) — jenis ini dipilih sekali
saat dibuat dan tidak bisa diganti lagi.

**Siapa boleh:** lihat/buat/ubah — Super Admin, Purchase, Accounting. Hapus
— Super Admin & Purchase. (Project Manager tidak punya akses.)

**Langkah:**
1. **Invoice Keluar → Tambah Invoice.**
2. Pilih **Client**, **Project** (opsional), **Jenis Invoice**, **Tanggal**,
   No. Kontrak/Tanggal, **Tagihan DP** (persentase dari master), **PPN%**,
   No. Faktur Pajak, Tanda Tangan.
3. Isi baris barang/jasa (dari Master Barang atau ketik bebas Deskripsi),
   Qty, Satuan, Harga.
4. **Total dihitung otomatis oleh sistem** (tidak bisa diketik manual):
   Jumlah = Qty × Harga per baris → Tagihan DP = Jumlah × persen DP → PPN =
   Tagihan DP × persen PPN → Total = Tagihan DP + PPN.

Invoice yang sudah masuk ke sebuah **Tanda Terima** tidak bisa dihapus lagi.
Invoice yang belum ditagih (belum ada Tanda Terima) muncul sebagai
pengingat di Dashboard.

---

## 13. Tanda Terima

**Untuk apa:** bukti serah-terima / tanda terima penagihan yang membungkus
satu atau beberapa Invoice Keluar sekaligus (harus dari Client yang sama).

**Siapa boleh:** lihat/buat/ubah — Super Admin, Purchase, Accounting. Hapus
— Super Admin saja.

**Langkah:**
1. **Tanda Terima → Tambah.**
2. Centang **Invoice Keluar** yang mau ditagihkan (hanya invoice yang
   belum masuk Tanda Terima lain yang muncul di pilihan, dan harus 1
   Client).
3. Isi **Tanggal**, **Penerima**, **Tanda Tangan**, catatan, dan (opsional)
   kaitkan tiap invoice dengan Surat Jalan terkait.
4. Simpan → halaman cetak langsung terbuka.

Client dan daftar invoice yang sudah dipilih **tidak bisa diganti** setelah
disimpan pertama kali — edit hanya bisa menambah/mengurangi invoice yang
sudah ada dari client yang sama.

---

## 14. Pembelian Offline

**Untuk apa:** pembelian tunai/lapangan di luar alur PO resmi (mis. beli
material mendadak di toko).

**Siapa boleh lihat:** Super Admin, Purchase, Accounting, Project Manager.
Buat/ubah — Super Admin, Purchase, Accounting. Hapus — Super Admin &
Purchase.

**Langkah:**
1. **Pembelian Offline → Tambah.**
2. Isi **Project**, **Nama Supplier** (ketik bebas), **Tanggal**, upload
   **Bukti Pembelian** & **Foto Barang**.
3. Isi baris barang (dari master atau ketik bebas), Satuan, Qty, Harga.

Sama seperti PO, transaksi ini juga bisa jadi **sumber** untuk Penerimaan
Barang. Begitu ada Penerimaan yang mengacu ke sini, transaksi **tidak bisa
dihapus lagi sama sekali**, oleh role mana pun.

---

## 15. Laporan

Pusat semua laporan — satu halaman, banyak jenis laporan yang bisa
difilter, dicetak (PDF), dan diekspor (Excel):

- **Purchase Order** — Detail & Rekap
- **Pembayaran**
- **Kas** (link ke Laporan Kas, lihat [Bagian 6.4](#64-kartu-saldo--cetak))
- **Penerimaan Barang**
- **Pengeluaran Barang**
- **Stok Barang** — Detail & Rekap (tersedia untuk lebih banyak role;
  Purchase melihat versi **tanpa kolom harga**, hanya Super Admin &
  Accounting yang melihat harga)
- **Stok Opname**
- **Invoice Keluar**
- **Pembelian Offline**
- **Riwayat Aktivitas** (audit log — khusus Super Admin & Accounting)

Role selain Super Admin/Purchase/Accounting (mis. PIC Project) hanya
melihat kartu **Stok Barang** di halaman ini.

Menu **Tutup Bulan** (khusus Super Admin) juga bisa diakses dari sini —
mengunci transaksi bulan tertentu per modul supaya tidak bisa diubah lagi
setelah tanggal tersebut.

---

## 16. User Management

*Khusus Super Admin.*

**Untuk apa:** kelola akun login & role.

**Langkah membuat user baru:**
1. **User Management → Tambah User.**
2. Isi **Role**, **Nama Lengkap**, **Username**, **Email**, **Password**.
3. Kalau role-nya Purchase/PIC Project/Admin Project, sistem **otomatis**
   membuatkan mapping **PIC Kas** untuknya (password Kas awal = sama dengan
   password login) — supaya user baru langsung bisa pakai Kas tanpa langkah
   setup tambahan.
4. Ada panel **"Hak Akses"** per-user untuk override izin tertentu di luar
   aturan role default-nya (mis. memberi 1 orang akses lebih/kurang dari
   role-nya).

User **tidak pernah dihapus permanen** lewat cara normal — nonaktifkan
lewat tombol status (Aktif/Nonaktif). Hapus (soft-delete, masuk Tempat
Sampah) hanya bisa Super Admin, dan tidak bisa untuk akun sendiri atau
Super Admin aktif terakhir.

---

## 17. Master Data

*Halaman induk untuk Super Admin & Accounting* — kumpulan data referensi
yang dipakai di seluruh aplikasi. Dari sini juga bisa lihat ringkasan Total
Supplier/Project/Barang/Gudang dan jumlah barang di bawah stok minimum.

### 17.1 Project

CRUD data project. Setiap project punya **Kode Prefix** sendiri (diatur di
Master Kode). Dari daftar Project, klik **⋮ → Akses Kas** untuk mengatur
user Purchase/PIC Project/Admin Project mana yang boleh lihat Kas project
tersebut (lihat [Bagian 6.2](#62-data-apa-yang-terlihat-per-role)).

### 17.2 Supplier & Client

CRUD data pemasok dan pelanggan: Nama, PIC/Kontak, Telepon, Email, Alamat
(Supplier juga punya NPWP), Status, Kode Prefix.

### 17.3 Gudang

CRUD lokasi gudang, dengan Kode Prefix sendiri.

### 17.4 Barang (Master Barang)

CRUD data barang: Nama, Kategori, **Jenis Stok** (Stok Proyek / Stok Lampu
/ Inventory Kantor — masing-masing punya pool kode sendiri), Satuan,
Spesifikasi, Stok Minimum, Status. Mengganti Jenis Stok pada barang yang
sudah ada otomatis menyesuaikan seluruh riwayat stoknya.

### 17.5 Kategori Barang & Satuan

Data referensi sederhana (nama saja) untuk dropdown kategori barang dan
satuan (Pcs, Meter, Roll, Kg, dst).

### 17.6 Kategori Kas

Kategori transaksi Kas (Biaya Operasional, Material Proyek, Inventory
Kantor, dll), termasuk penanda **"menyentuh stok"** yang menentukan apakah
baris Kas berkategori itu ikut menambah stok barang.

### 17.7 PIC Kas

Pemetaan user login → identitas "PIC" di Kas + kredensial + prefix nomor
bukti Kas-nya. Ini yang menentukan Kas siapa yang tampil sebagai pilihan
"PIC" saat membuat transaksi Kas.

### 17.8 Master Bank & Master Rekening

Dua data terpisah: **Master Bank** untuk dropdown Bank, **Master Rekening**
untuk dropdown Rekening di form Kas.

### 17.9 Master Kode

Pengaturan awalan/prefix nomor otomatis untuk Barang (3 kelompok), Supplier,
Client, Gudang, Project, dan Bank Masuk/Keluar — termasuk panjang digit dan
"kode master" (akhiran) tiap kelompok. Bisa punya lebih dari 1 prefix per
kelompok.

---

## 18. Tempat Sampah

*Khusus Super Admin.*

Semua penghapusan di aplikasi ini **tidak langsung hilang** — masuk ke
Tempat Sampah dulu (soft-delete), mencakup hampir semua modul transaksi &
master data.

- **Pulihkan** — mengembalikan data (kalau itu transaksi Kas yang tadinya
  menambah stok, stoknya ikut ditambahkan kembali).
- **Hapus Permanen** (1 baris) — hanya bisa kalau tidak sedang dipakai data
  lain yang masih aktif.
- **Kosongkan** — hapus permanen banyak sekaligus; sistem otomatis
  mengurutkan berdasarkan ketergantungan data (mis. PO → Pembayaran →
  Penerimaan dihapus berurutan dalam 1 klik), baris yang masih terkait data
  aktif akan dilewati & dilaporkan.

---

## 19. Pengaturan Sistem

*Khusus Super Admin.*

- **Profil Perusahaan** — nama, alamat, telepon, email, NPWP, logo, dan
  stempel perusahaan (dipakai di semua cetakan dokumen).
- **Penomoran Dokumen** — atur prefix nomor otomatis untuk 12 jenis dokumen.
- **Rekening Bank** — daftar rekening yang tampil di cetakan Invoice Keluar
  (hanya 1 yang aktif dipakai).
- **Waktu Sesi** — atur berapa lama sesi Kas boleh idle sebelum terkunci
  otomatis.
- **Notifikasi** — nyala/matikan 6 jenis notifikasi push.
- **Hak Akses** — matriks izin per-role yang bisa diedit langsung dari sini
  (menentukan menu & aksi apa yang boleh dilakukan tiap role). Modul
  `Pengaturan Sistem`, `User Management`, `Tempat Sampah`, dan `Tutup Bulan`
  **selalu terkunci khusus Super Admin**, tidak bisa diubah lewat matriks
  ini (demi keamanan).
- **Backup Database** — unduh salinan database kapan saja lewat tombol di
  halaman ini.

---

## 20. Fitur yang sama di semua modul

- **Filter & Cari** — hampir semua daftar punya kotak pencarian + filter
  tanggal/kategori/status di bagian atas tabel.
- **Cetak & Export** — tombol **PDF** (cetak/unduh) dan **Excel** (unduh
  spreadsheet) tersedia di hampir semua laporan & dokumen transaksi.
- **Hapus per Rentang Tanggal** (khusus Super Admin) — hapus massal
  berdasarkan rentang tanggal di halaman daftar transaksi, alih-alih satu
  per satu.
- **Foto/File upload** — foto otomatis dikompres (maks ±1920px), PDF
  otomatis dikompres kalau ukurannya besar (kalau tersedia di server) —
  tidak perlu kompres manual sebelum upload.
- **Notifikasi push** — kalau HP/browser mengizinkan, Anda akan dapat
  notifikasi otomatis untuk hal yang perlu ditindaklanjuti (Validasi Kas
  menunggu, Selisih Barang, dll) meski aplikasi sedang tidak dibuka.
- **Tampilan HP** — semua tabel otomatis berubah jadi bentuk kartu di layar
  kecil, dan halaman cetak (PDF preview) otomatis di-zoom supaya terlihat
  utuh seperti hasil cetak aslinya.

---

## 21. Istilah-istilah penting

| Istilah | Artinya |
|---|---|
| **No. Bukti / No. Dokumen** | Nomor otomatis yang dibuat sistem saat data disimpan, format umum `001/KODE.HME/BULAN-ROMAWI/TAHUN` (mis. `005/PO.HME/IX/2026`). Tidak bisa diisi manual, dan nomor baru "dibakar" (terpakai permanen) hanya saat tombol **Simpan** ditekan — membuka form saja tidak memakai nomor. |
| **Validasi** | Langkah persetujuan sebelum sebuah data dianggap final/mempengaruhi stok atau saldo. Ada di Validasi Barang (Penerimaan → stok) dan Validasi Kas (transaksi Kas → status final). |
| **Divisi (Kas)** | Pengelompokan otomatis transaksi Kas berdasarkan role pembuatnya: Purchase, Accounting, Project, atau Umum — menentukan siapa yang berhak memvalidasi & melihat kartu saldonya. |
| **Soft-delete** | "Hapus" di aplikasi ini sebenarnya memindahkan data ke Tempat Sampah, bukan menghapus permanen — aman dari salah klik. |
| **Tutup Bulan** | Mengunci transaksi sampai tanggal tertentu supaya tidak bisa diubah/dihapus lagi — dipakai untuk menjaga laporan bulan yang sudah selesai. |
| **Hak Akses / Role** | Aturan menu & aksi apa yang boleh dilakukan tiap jenis akun — lihat [Bagian 2](#2-peran-role--siapa-boleh-apa). |
| **PWA** | Cara memasang aplikasi web ini di HP seperti aplikasi biasa, tanpa lewat toko aplikasi. |

---

## 22. Pertanyaan umum

**Kenapa menu tertentu tidak muncul di sidebar saya?**
Karena role Anda memang tidak diberi akses ke modul itu. Hubungi Super
Admin kalau merasa harusnya bisa akses.

**Saya salah hapus data, bagaimana?**
Buka **Tempat Sampah** (Super Admin) — data yang terhapus masih ada di sana
dan bisa **Dipulihkan**.

**Kenapa Kas saya kosong padahal ada transaksinya?**
Kemungkinan besar akun Anda (role Purchase/PIC Project/Admin Project)
belum diberi akses ke project terkait. Minta Super Admin menambahkan lewat
**Master Data → Project → ⋮ → Akses Kas**.

**Kenapa saya tidak bisa mengubah/menghapus transaksi Kas tertentu?**
Kemungkinan transaksi itu sudah **Tervalidasi** (terkunci) — hanya Super
Admin yang bisa mengubahnya lagi. Atau, transaksi itu tanggalnya masuk
periode yang sudah **Ditutup** (Tutup Bulan).

**Kenapa stok tidak bertambah setelah saya input Penerimaan Barang?**
Stok baru bertambah setelah barangnya **divalidasi** di menu **Validasi
Barang** — Penerimaan Barang sendiri belum langsung menambah stok.

**Saya butuh nomor project/supplier/client baru tapi tidak ada menunya —
bagaimana?**
Banyak form (PO, Kas, dll) punya tombol **"+" (Tambah Cepat)** di sebelah
dropdown Project/Supplier/Client/Barang — bisa langsung membuat data baru
tanpa keluar dari form yang sedang diisi.

---

© PT. Hexa Multi Energi. Panduan ini untuk pemakaian internal.
