HEXA STOK -- pintasan desktop (Windows)
=======================================

Isi folder:
  HEXA STOK (Layar Penuh).bat   -> layar penuh, TANPA bingkai & tanpa tombol
                                   apa pun. Keluar dengan Alt + F4.
  HEXA STOK (Jendela).bat       -> jendela aplikasi (tanpa address bar),
                                   tombol minimize/maximize/silang masih ada.

CARA PAKAI
----------
1. Salin folder "desktop" ini ke komputer (mis. ke Documents).
2. Double-click salah satu .bat sesuai kebutuhan.
   - Pertama kali: Chrome/Edge membuat profil baru khusus HEXA STOK,
     lalu minta login. Login sekali, seterusnya ingat sendiri.

BUAT PINTASAN DI DESKTOP / TASKBAR
---------------------------------
1. Klik kanan file .bat -> "Send to" -> "Desktop (create shortcut)".
2. (Opsional) klik kanan shortcut -> Properties -> "Change Icon..."
   -> arahkan ke file logo, atau biarkan default.
3. Klik kanan shortcut -> "Pin to taskbar" kalau mau nempel di taskbar.

CATATAN
-------
- Butuh Google Chrome ATAU Microsoft Edge terpasang (dicek otomatis).
- Butuh koneksi internet.
- Profil disimpan di:  %LOCALAPPDATA%\HexaStokApp
  Hapus folder itu kalau mau reset login / mulai bersih.
- Mode "Layar Penuh" memakai --kiosk: tidak bisa di-minimize dan menutupi
  taskbar. Cocok untuk PC khusus operasional/gudang. Untuk PC kerja
  sehari-hari, pakai "HEXA STOK (Jendela).bat".
- Kalau URL berubah (mis. pindah domain), sunting baris  set "URL=..."
  di dalam file .bat.
