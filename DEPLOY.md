# Panduan Deploy

Panduan deploy lengkap & terperinci sudah dipindah ke **[README.md](README.md)**
(bagian **"3. LANGKAH DEPLOY — dari nol sampai online"** dan seterusnya).

Ringkasan skrip di folder [`deploy/`](deploy/):

| Skrip | Kegunaan |
|---|---|
| `setup.sh` | 1× di VPS baru — pasang stack + deploy pertama |
| `update.sh` | tiap ada `git push` — tarik & terapkan perubahan |
| `backup.sh` | dijadwalkan cron harian — dump DB + arsip uploads |
| `migrate-server.sh` | pindah data VPS lama → VPS baru |

Perintah tercepat:

```bash
# setup pertama (di VPS baru)
git clone https://github.com/adedian/Stok-proyek.git /tmp/skp
bash /tmp/skp/deploy/setup.sh
sudo certbot --apache -d SUBDOMAIN

# update rutin (setelah git push dari laptop)
ssh ubuntu@IP_VPS "bash /var/www/stok/deploy/update.sh"
```
