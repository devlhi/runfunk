# Gong Fun Run 2026

Situs pendaftaran dan panel panitia untuk **Gong Fun Run 2026** — fun run 5K & 10K,
start & finis di Lapangan Tuladenggi, Kabupaten Gorontalo. Diselenggarakan oleh
**IKA — Ikatan Keluarga Alumni SMK Gotong Royong Telaga**.

**Dibangun dengan:** Laravel 12 · Inertia.js · Vue 3 · Vite · MySQL

---

## Daftar Isi

- [Yang Bisa Dilakukan](#yang-bisa-dilakukan)
- [Kebutuhan Server](#kebutuhan-server)
- [Pasang di Komputer Sendiri (Laragon)](#pasang-di-komputer-sendiri-laragon)
- [Pasang di VPS (Ubuntu + Nginx)](#pasang-di-vps-ubuntu--nginx)
- [Pasang lewat aaPanel](#pasang-lewat-aapanel)
- [Dua Proses Latar yang Wajib Jalan](#dua-proses-latar-yang-wajib-jalan)
- [Daftar Isian `.env`](#daftar-isian-env)
- [Akun Bawaan](#akun-bawaan)
- [Catatan Teknis](#catatan-teknis)
- [SEO & Mesin Pencari](#seo--mesin-pencari)
- [Perawatan](#perawatan)
- [Masalah yang Sering Terjadi](#masalah-yang-sering-terjadi)

---

## Yang Bisa Dilakukan

### Peserta

- Daftar akun, lalu verifikasi email lewat kode 6 angka **atau** tombol sekali klik
- Ambil slot 5K atau 10K, unggah bukti pembayaran
- Lihat e-tiket, nomor BIB, dan e-sertifikat setelah waktu finisnya dicatat
- Alihkan slot ke orang lain sampai H-7

### Panitia

- Verifikasi pembayaran — dari bukti unggahan maupun konfirmasi manual (tunai ke panitia)
- **Pindai QR** nomor BIB untuk membagikan race pack dan mencatat kehadiran
- Cetak nomor BIB massal; pindai kartu panitia untuk memeriksa keasliannya
- Catat waktu finis — peringkat keseluruhan dan per gender dihitung otomatis
- Kelola berita, pengumuman, sponsor, kategori & kuota
- Sebar pengumuman lewat WhatsApp (gateway mpedia) dan email

### Developer

Semua wewenang panitia, **ditambah**:

- Kelola akun pengelola, pengaturan acara, gateway email & WhatsApp
- Sunting isi template email dengan pratinjau langsung
- Cetak kartu panitia ber-QR, dan terbitkan ulang kartu yang hilang

Developer sengaja mewarisi seluruh wewenang panitia supaya tidak perlu dua akun terpisah
untuk tugas harian. Dua pengaman dipasang agar panel tidak bisa terkunci selamanya:
**developer terakhir tidak bisa menurunkan perannya sendiri, dan tidak bisa menghapus
akunnya sendiri.**

---

## Kebutuhan Server

| Kebutuhan | Versi minimum |
| --- | --- |
| PHP | 8.2 |
| MySQL / MariaDB | 8.0 / 10.6 |
| Composer | 2.x |
| Node.js | 20 |
| Ekstensi PHP | `gd`, `dom`, `mbstring`, `pdo_mysql`, `openssl`, `fileinfo`, `zip`, `curl`, `bcmath` |

> **`gd` dan `dom` wajib ada.** `gd` memproses pas foto panitia, `dom` menyaring isi
> template email. Tanpa keduanya, fitur itu gagal **saat dipakai**, bukan saat dipasang —
> jadi mudah terlewat sampai hari-H.

---

## Pasang di Komputer Sendiri (Laragon)

```bash
git clone https://github.com/devlhi/runfunk.git funrun
cd funrun

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat basis data kosong bernama `funrun` lewat HeidiSQL bawaan Laragon, lalu sesuaikan
`.env`:

```env
DB_DATABASE=funrun
DB_USERNAME=root
DB_PASSWORD=
APP_TIMEZONE=Asia/Makassar
```

Jalankan migrasi berikut data contohnya:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

Buka `http://funrun.test` — Laragon membuat vhost otomatis dan sudah mengarah ke folder
`public/`. Atau jalankan `php artisan serve` lalu buka `http://localhost:8000`.

Mengulang dari nol:

```bash
php artisan migrate:fresh --seed
```

> **Untuk mencoba pemindai QR**, buka lewat `http://localhost:8000` — bukan
> `http://funrun.test`. Peramban hanya mengizinkan kamera di `https://` atau `localhost`.

---

## Pasang di VPS (Ubuntu + Nginx)

### 1. Siapkan paketnya

```bash
sudo apt update && sudo apt install -y nginx mysql-server unzip git \
  php8.2-fpm php8.2-mysql php8.2-gd php8.2-xml php8.2-mbstring \
  php8.2-curl php8.2-zip php8.2-bcmath
```

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs
```

### 2. Buat basis data

```bash
sudo mysql -e "CREATE DATABASE funrun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'funrun'@'localhost' IDENTIFIED BY 'GANTI_SANDI_INI';"
sudo mysql -e "GRANT ALL ON funrun.* TO 'funrun'@'localhost'; FLUSH PRIVILEGES;"
```

### 3. Ambil kodenya

```bash
cd /var/www
sudo git clone https://github.com/devlhi/runfunk.git funrun
cd funrun

sudo chown -R $USER:www-data /var/www/funrun
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 4. Atur `.env`

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Yang **wajib** diubah untuk produksi:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainanda.id
APP_TIMEZONE=Asia/Makassar

DB_DATABASE=funrun
DB_USERNAME=funrun
DB_PASSWORD=sandi_yang_tadi_dibuat

SESSION_SECURE_COOKIE=true
```

> **`APP_DEBUG=false` tidak bisa ditawar.** Kalau `true` di produksi, halaman galat
> menampilkan kredensial basis data ke pengunjung. Aplikasi ini sengaja **menolak jalan**
> kalau keduanya salah, dengan pesan yang menjelaskan cara memperbaikinya.

### 5. Migrasi & hak akses

```bash
php artisan migrate --force
php artisan db:seed --force        # akun bawaan + kategori 5K/10K
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 6. Vhost Nginx

```nginx
server {
    listen 80;
    server_name domainanda.id www.domainanda.id;

    # WAJIB menunjuk ke /public, bukan ke akar proyek. Kalau salah, berkas .env
    # berisi sandi basis data bisa diunduh siapa saja lewat peramban.
    root /var/www/funrun/public;
    index index.php;

    charset utf-8;
    client_max_body_size 8M;   # bukti bayar sampai 4 MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/funrun /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 7. HTTPS — bukan pilihan

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domainanda.id -d www.domainanda.id
```

HTTPS wajib bukan cuma demi keamanan: **pemindai QR tidak akan menyala tanpa itu.**
Peramban menutup akses kamera di koneksi `http://` biasa.

### 8. Optimalkan

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ulangi ketiganya setiap kali `.env` atau kode berubah.

---

## Pasang lewat aaPanel

1. **Website → Add site** — isi domainnya, pilih **PHP 8.2**, dan buat basis data
   sekalian di formulir yang sama.

2. **Ubah akar dokumen.** Langkah paling sering terlewat dan paling berbahaya:

   > Website → *domain* → **Site directory** → **Running directory** → pilih **`/public`**

   Kalau tidak diubah, berkas `.env` berisi sandi basis data bisa diunduh siapa pun.

3. **Aktifkan ekstensi PHP.** App Store → PHP 8.2 → Setting → Install extensions:
   `gd`, `dom/xml`, `mbstring`, `fileinfo`, `zip`, `curl`, `bcmath`

4. **Buka fungsi yang diblokir.** PHP 8.2 → Setting → Disabled functions — hapus
   `putenv`, `proc_open`, `pcntl_signal`, `symlink` dari daftar. Laravel dan Composer
   membutuhkannya.

5. **Ambil kodenya** lewat Terminal aaPanel:

   ```bash
   cd /www/wwwroot/domainanda.id
   git clone https://github.com/devlhi/runfunk.git .
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   cp .env.example .env && php artisan key:generate
   ```

6. **Isi `.env`** — sama seperti bagian VPS di atas.

7. **Migrasi & hak akses:**

   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   chown -R www:www storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

8. **Aturan rewrite.** Website → *domain* → URL Rewrite → pilih **laravel5**.

9. **SSL.** Website → *domain* → SSL → Let's Encrypt → Apply, lalu nyalakan
   **Force HTTPS**.

10. **Pasang dua proses latar** — lihat bagian berikutnya. Di aaPanel keduanya diatur
    lewat **App Store → Supervisor** dan menu **Cron**.

---

## Dua Proses Latar yang Wajib Jalan

Tanpa keduanya, situs tetap terbuka dan terlihat normal — tapi **dua hal penting
diam-diam tidak terjadi**, tanpa pesan galat apa pun.

### 1. Pekerja antrean — kabar pembayaran

Email "pembayaran disetujui" beserta nomor BIB dikirim lewat antrean. Tanpa pekerja,
email itu **menumpuk di basis data dan tidak pernah terkirim** — peserta tidak akan
pernah tahu pembayarannya sudah diterima.

**Systemd (VPS):**

```ini
# /etc/systemd/system/funrun-queue.service
[Unit]
Description=Antrean Gong Fun Run
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/www/funrun/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now funrun-queue
sudo systemctl status funrun-queue
```

**aaPanel:** App Store → **Supervisor** → Add Daemon

| Isian | Nilai |
| --- | --- |
| Name | `funrun-queue` |
| Run User | `www` |
| Run Dir | `/www/wwwroot/domainanda.id` |
| Start Command | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| Processes | `1` |

### 2. Penjadwal — pelepas slot kedaluwarsa

Slot yang tidak dibayar dalam batas waktu harus dilepas supaya kuotanya kembali
tersedia. Tanpa penjadwal, slot itu **mengunci kuota selamanya**.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/funrun && php artisan schedule:run >> /dev/null 2>&1
```

**aaPanel:** Cron → Add Cron → *Shell Script*, tiap **1 menit**:

```bash
cd /www/wwwroot/domainanda.id && php artisan schedule:run
```

**Memastikan keduanya benar-benar jalan:**

```bash
php artisan queue:work --once     # harus memproses satu pekerjaan, bukan menggantung
php artisan schedule:list         # harus menampilkan funrun:release-expired
```

---

## Daftar Isian `.env`

| Kunci | Keterangan |
| --- | --- |
| `APP_ENV` | `production` di server |
| `APP_DEBUG` | **`false`** di server — aplikasi menolak jalan kalau `true` |
| `APP_URL` | Alamat lengkap berikut `https://` |
| `APP_TIMEZONE` | `Asia/Makassar` (WITA) — jangan dikosongkan |
| `DB_*` | Sambungan basis data |
| `SESSION_SECURE_COOKIE` | `true` begitu HTTPS aktif |
| `QUEUE_CONNECTION` | `database` (bawaan) |
| `MAIL_MAILER` | `log` untuk uji coba, `smtp` untuk sungguhan |

> Pengaturan **SMTP dan WhatsApp tidak diisi di `.env`.** Keduanya diatur lewat panel di
> **Pengaturan Acara**, supaya panitia bisa mengubahnya tanpa menyentuh server. Di situ
> juga ada tombol uji kirim untuk keduanya.

---

## Akun Bawaan

Dibuat oleh `db:seed`:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Developer | `developer@gongfunrun.id` | `developer123` |
| Panitia | `panitia@gongfunrun.id` | `panitia123` |
| Peserta | `peserta@example.com` | `peserta123` |

> **Ganti ketiganya sebelum situs dipakai sungguhan.** Sandi ini tertulis di kode dan bisa
> dibaca siapa saja. Ubah lewat **Kelola Pengguna**, terutama akun developer — akun itu
> yang memegang pengaturan nomor rekening pembayaran.

Untuk memasang tanpa data contoh, jalankan `php artisan migrate --force` saja, lalu buat
akun developer pertama lewat `php artisan tinker`.

---

## Catatan Teknis

**Pengaturan acara disimpan di basis data, bukan `.env`.** Nama acara, tanggal, lokasi,
rekening pembayaran, batas waktu bayar, dan sambutan Ketua IKA bisa diubah lewat
antarmuka tanpa restart. Selama belum pernah disimpan, nilainya jatuh ke bawaan di
`config/funrun.php` — situs tetap jalan.

**Kolom `role` bertipe ENUM.** Menambah peran baru **wajib** lewat migrasi
(`ALTER TABLE ... MODIFY role ENUM(...)`). Menambah konstanta di model saja tidak cukup —
MySQL akan menolak menyimpannya.

**Kode QR ditandatangani.** Isi QR bukan nomor BIB telanjang, melainkan token ber-HMAC.
Kalau isinya cuma nomor BIB, siapa pun bisa mencetak kartu palsu — dan nomor BIB memang
tercetak besar-besar untuk dilihat semua orang.

**Kartu panitia punya nomor versi.** Menekan "Terbitkan Ulang" menaikkan versinya,
sehingga kartu lama langsung ditolak saat dipindai. Itu satu-satunya cara menonaktifkan
kartu yang hilang tanpa menghapus akun orangnya.

**Bukti bayar dan pas foto disimpan di disk privat**, bukan folder publik, dan disajikan
lewat rute berautentikasi. Keduanya **tidak ikut di repositori ini**.

**Halaman berisi data orang tertutup dari mesin pencari.** Dashboard peserta, e-tiket,
sertifikat, bukti bayar, dan seluruh panel panitia menjawab dengan tajuk
`X-Robots-Tag: noindex` sekaligus tag `<meta name="robots">`. Ditandai lewat middleware
di grup rutenya, jadi halaman baru otomatis ikut terlindungi tanpa harus diingat.

`robots.txt` dan `sitemap.xml` dilayani lewat rute, bukan berkas statis — alamat di
dalamnya mengikuti `APP_URL`, jadi tidak perlu disunting saat pindah domain.

> Sitemap hanya memuat beranda, daftar berita, papan hasil, dan berita yang **sudah
> tayang**. Draf berita tidak ikut.

---

## SEO & Mesin Pencari

Bagian ini untuk yang mengurus agar acaranya mudah ditemukan. Sisi teknisnya sudah
ditanam di kode — yang di bawah ini menjelaskan apa yang sudah ada, dan apa yang **hanya
bisa dilakukan Anda** karena butuh akun pihak luar.

### Yang Sudah Otomatis

Tidak perlu disentuh — semuanya mengikuti `APP_URL` dan pengaturan acara:

| Bagian | Keterangan |
| --- | --- |
| `robots.txt` | `https://domainanda.id/robots.txt` — memblokir panel & data peserta, menunjuk sitemap |
| `sitemap.xml` | `https://domainanda.id/sitemap.xml` — beranda, berita, hasil, tiap berita tayang |
| Meta description | Diambil dari nama & tanggal acara di Pengaturan Acara |
| Open Graph | Pratinjau bergambar saat tautan dibagi di WhatsApp/Facebook/Telegram |
| Data terstruktur | Kartu acara `SportsEvent` di beranda — tanggal & lokasi bisa muncul di Google |
| `noindex` | Dashboard, e-tiket, sertifikat, bukti bayar, panel panitia — tertutup dari pencarian |

> **Berita adalah mesin SEO situs ini.** Beranda cuma satu halaman; tiap berita yang
> panitia terbitkan jadi satu alamat baru yang bisa dicari. Rutin menulis kabar persiapan
> — "Pengambilan race pack H-2", "Rute 10K resmi" — jauh lebih ampuh menaikkan posisi di
> Google daripada trik teknis apa pun.

### Yang Harus Anda Lakukan Sendiri

Butuh akun Google, jadi tidak bisa ditanam di kode:

**1. Daftarkan ke Google Search Console** — inilah langkah paling menentukan.

1. Buka <https://search.google.com/search-console> → **Add property** → **URL prefix** →
   masukkan `https://domainanda.id`
2. Verifikasi kepemilikan. Cara termudah: pilih **HTML tag**, salin yang diberikan Google
   (`<meta name="google-site-verification" content="xxxx...">`), lalu tempel di
   **Pengaturan Acara → Mesin Pencari (SEO) → Kode Verifikasi Google** dan simpan. Boleh
   tempel seluruh tag atau kodenya saja — keduanya diterima. Kembali ke Search Console,
   klik **Verify**.
3. Setelah terverifikasi: menu **Sitemaps** → ketik `sitemap.xml` → **Submit**
4. Tunggu beberapa hari; Google mulai merayapi dan situsnya muncul di hasil pencarian

**2. Uji pratinjau tautan** sebelum menyebarkannya:

- Facebook/WhatsApp: <https://developers.facebook.com/tools/debug/> — tempel URL situs,
  pastikan gambar pelari dan judulnya muncul. Kalau salah, klik **Scrape Again**.
- Kartu acara Google: <https://search.google.com/test/rich-results> — tempel URL beranda,
  pastikan `SportsEvent` terbaca tanpa galat.

**3. Google Bisnisku (opsional tapi berdampak)** — kalau IKA punya alamat tetap, daftarkan
di <https://business.google.com>. Pencarian "fun run gorontalo" jadi memunculkan kartu
lokasi berikut tautan pendaftaran.

> **Alternatif verifikasi.** Kalau lebih suka tidak lewat panel, Search Console juga
> menerima **DNS record** (tambah TXT di pengaturan domain) atau unggah **berkas HTML**
> dari Google ke folder `public/`. Ketiganya sah — pilih yang paling mudah.

### Memeriksa Sendiri

Setelah situs live, pastikan semuanya benar:

```bash
curl -s https://domainanda.id/robots.txt      # harus berisi banyak baris Disallow
curl -s https://domainanda.id/sitemap.xml     # harus <urlset> berisi beranda & berita

# Halaman peserta HARUS menolak diindeks:
curl -sI https://domainanda.id/dashboard | grep -i x-robots-tag
```

Kalau `robots.txt` cuma berisi `Disallow:` satu baris tanpa nilai, berarti masih memakai
berkas statis lama — hapus `public/robots.txt` supaya rutenya yang dipakai.

---

## Perawatan

**Memperbarui kode:**

```bash
cd /var/www/funrun
php artisan down

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart funrun-queue    # pekerja memuat kode lama sampai dimulai ulang

php artisan up
```

**Cadangan.** Belum ada cadangan otomatis. Dengan 3.000 slot, uang yang berputar ratusan
juta — pasang cron harian:

```cron
0 2 * * * mysqldump -u funrun -p'SANDI' funrun | gzip > /var/backups/funrun-$(date +\%F).sql.gz
```

Ikut cadangkan `storage/app/private/` — di situ bukti pembayaran dan pas foto panitia
disimpan, dan berkas itu tidak ada di repositori.

---

## Masalah yang Sering Terjadi

**Pemindai QR tidak menyala / "kamera diblokir"**
Situs dibuka lewat `http://` biasa. Peramban hanya membuka kamera di `https://` atau
`localhost`. Pasang SSL, atau tandai peserta manual lewat pencarian nomor BIB.

**Peserta tidak menerima email apa pun**
Pekerja antrean belum jalan — lihat [Dua Proses Latar](#dua-proses-latar-yang-wajib-jalan).
Periksa dengan `php artisan queue:work --once`. Kalau gateway email belum diisi, email
hanya ditulis ke `storage/logs/laravel.log`.

**Jam di panel meleset 8 jam**
`APP_TIMEZONE` belum diisi `Asia/Makassar`, atau `php artisan config:cache` belum
dijalankan ulang setelah `.env` diubah.

**Kuota habis padahal peserta sedikit**
Penjadwal belum jalan, jadi slot yang tidak dibayar tidak pernah dilepas. Jalankan
`php artisan funrun:release-expired` sekali untuk membersihkan, lalu pasang cron-nya.

**Halaman putih / galat 500 setelah dipasang**
Hampir selalu soal hak akses:
`chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache`

**Gambar sampul berita tidak muncul**
`php artisan storage:link` belum dijalankan.

**Warna 5K/10K hilang saat kartu BIB dicetak**
Nyalakan "Background graphics" di dialog cetak peramban. Kartunya sudah meminta warna
latar ikut dicetak, tapi sebagian peramban tetap butuh centang itu.

---

## Menjalankan Pengujian

```bash
php artisan test
```

**341 pengujian** mencakup alur pendaftaran, pembayaran, verifikasi email, keaslian kode
QR, kartu panitia, hak akses tiap peran, kebocoran data pribadi, dan halaman mana saja
yang boleh masuk mesin pencari.

Pengujian memakai basis data terpisah — buat `funrun_test` lebih dulu, atau sesuaikan
`phpunit.xml`.

---

## Lisensi

Dibuat untuk IKA — Ikatan Keluarga Alumni SMK Gotong Royong Telaga, Gorontalo.
