# Panduan Deployment Production JBLU Office (Ubuntu Server)

Dokumen ini merupakan panduan resmi langkah demi langkah (*runbook*) untuk melakukan deployment sistem **JBLU Office** pada server berbasis **Ubuntu 22.04 LTS / 24.04 LTS**.

---

## 1. Ringkasan Arsitektur & Spesifikasi Kebutuhan

Sistem JBLU Office dibangun di atas stack teknologi berikut:
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 15+ / 16 (Wajib timezone **UTC**)
- **Web Server**: Nginx (Reverse Proxy & Static Cache dengan HTTPS/TLS)
- **Process Manager**: Supervisor (menjalankan Queue Worker untuk antrean `pdf` dan `default`)
- **PDF Renderer**: Headless Chromium / Chrome (`--headless=new` tanpa sandbox/GPU)
- **Frontend Assets**: Vite (Node.js 20+ LTS)
- **Task Scheduler**: Linux Cron (`schedule:run` setiap menit)
- **Identitas**: SSO OAuth2/OIDC integration
- **Storage**:
  - `storage/app/public` di-symlink ke `public/storage` (Company logo, company stamp 28mm, user signatures)
  - `storage/app/private/documents` (Private disk untuk file PDF quotation final)

---

## 2. Langkah 1: Persiapan Server & Paket Dasar

Login ke server Ubuntu Anda via SSH sebagai user dengan hak akses `sudo`:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip zip software-properties-common ufw supervisor
```

### Konfigurasi Timezone Host (Wajib UTC)
Sesuai standar operasional sistem, timezone sistem host disetel ke UTC:

```bash
sudo timedatectl set-timezone UTC
sudo timedatectl set-ntp on
timedatectl
```

---

## 3. Langkah 2: Instalasi PHP 8.2 & Ekstensi yang Dibutuhkan

Tambahkan repositori PPA Ondřej Surý dan pasang PHP 8.2 FPM beserta seluruh ekstensi yang dibutuhkan oleh Laravel dan PostgreSQL:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.2-fpm php8.2-cli php8.2-pgsql php8.2-curl \
    php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath php8.2-intl \
    php8.2-gd php8.2-redis php8.2-common
```

Pastikan PHP dan PHP-FPM aktif:
```bash
php -v
sudo systemctl status php8.2-fpm
```

### Konfigurasi `php.ini` untuk Production
Edit file konfigurasi `/etc/php/8.2/fpm/php.ini` dan `/etc/php/8.2/cli/php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 180
date.timezone = UTC
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

---

## 4. Langkah 3: Instalasi PostgreSQL & Konfigurasi Database

Pasang PostgreSQL:

```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

Masuk ke user `postgres` dan buat database serta role untuk aplikasi:

```bash
sudo -u postgres psql
```

Jalankan query SQL berikut (ganti `'password_rahasia_anda'` dengan password yang kuat):

```sql
CREATE DATABASE office;
CREATE USER office_user WITH ENCRYPTED PASSWORD 'password_rahasia_anda';
GRANT ALL PRIVILEGES ON DATABASE office TO office_user;
ALTER DATABASE office OWNER TO office_user;

-- Pastikan timezone database adalah UTC
ALTER DATABASE office SET timezone TO 'UTC';

-- Berikan izin pada schema public (PostgreSQL 15+)
\c office
GRANT ALL ON SCHEMA public TO office_user;

\q
```

---

## 5. Langkah 4: Instalasi Composer & Node.js (Vite Build)

### 1. Pasang Composer (v2)
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 2. Pasang Node.js 20 LTS & NPM
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

---

## 6. Langkah 5: Instalasi Chromium & Font (Untuk Worker PDF)

Penerbitan dokumen PDF resmi quotation menggunakan Chromium headless. Pasang Chromium dan pustaka font pendukung:

```bash
# Untuk Ubuntu 22.04 / 24.04:
sudo apt install -y chromium-browser fonts-liberation fonts-dejavu-core libasound2 libgbm1 libnss3
```

> **Catatan**: Jika pada Ubuntu versi Anda perintah di atas memasang wrapper snap, pastikan path binary executable dapat diakses atau pasang Chromium via paket native:
```bash
which chromium-browser || which chromium || which google-chrome
```
Catat lokasi binary (biasanya `/usr/bin/chromium-browser` atau `/usr/bin/chromium`). Path ini akan dimasukkan ke `OFFICE_CHROME_BINARY` di file `.env`.

---

## 7. Langkah 6: Clone Aplikasi & Setup Hak Akses Direktori

Buat direktori kerja di `/var/www/office`:

```bash
sudo mkdir -p /var/www/office
sudo chown -R $USER:$USER /var/www/office
```

Clone kode sumber dari repository Git:

```bash
git clone <URL_GIT_REPOSITORY_ANDA> /var/www/office
cd /var/www/office
```

### Konfigurasi Kepemilikan & Hak Akses
Web server Nginx dan PHP-FPM berjalan sebagai user `www-data`. Berikan kepemilikan direktori `storage` dan `bootstrap/cache` ke `www-data`:

```bash
sudo chown -R www-data:www-data /var/www/office/storage /var/www/office/bootstrap/cache
sudo chmod -R 775 /var/www/office/storage /var/www/office/bootstrap/cache

# Buat direktori private documents dan temp jika belum ada
sudo -u www-data mkdir -p /var/www/office/storage/app/private/documents
sudo -u www-data mkdir -p /var/www/office/storage/app/private/tmp
sudo -u www-data mkdir -p /var/www/office/storage/app/public/company-stamps
sudo -u www-data mkdir -p /var/www/office/storage/app/public/user-signatures
```

---

## 8. Langkah 7: Konfigurasi Environment Production (`.env`)

Salin file template `.env`:

```bash
cp .env.example .env
nano .env
```

Sesuaikan variabel environment production berikut dengan cermat:

```dotenv
APP_NAME="JBLU Office"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://office.domainanda.com
APP_TIMEZONE=UTC

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=info

# Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=office
DB_USERNAME=office_user
DB_PASSWORD=password_rahasia_anda

# Session Database & Security
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Storage Disks
FILESYSTEM_DISK=local
OFFICE_DOCUMENT_DISK=documents

# PDF Worker & Headless Chromium
OFFICE_CHROME_BINARY=/usr/bin/chromium-browser
OFFICE_PDF_TIMEOUT=120

# Antrean Queue (Database)
QUEUE_CONNECTION=database
PDF_QUEUE_CONNECTION=database
PDF_QUEUE=pdf
DB_QUEUE=default
QUEUE_AFTER_COMMIT=true

# Timezone Bisnis JBLU (WIB)
OFFICE_BUSINESS_TIMEZONE=Asia/Jakarta
OFFICE_QUOTATION_DOCUMENT_TYPE_CODE=QUOTATION

# Konfigurasi SSO JBLU
OFFICE_SSO_BASE_URL=https://sso.domainanda.com
OFFICE_SSO_CLIENT_ID=client_id_dari_sso
OFFICE_SSO_CLIENT_SECRET=client_secret_minimal_16_karakter
OFFICE_SSO_REDIRECT_URI=https://office.domainanda.com/auth/callback
OFFICE_SSO_SCOPES="openid profile email"
OFFICE_SSO_TENANT_ID=tenant_office
OFFICE_SSO_SESSION_MAX_MINUTES=480

# Bootstrap Initial System Admin SSO (Opsional)
OFFICE_BOOTSTRAP_ADMIN_SSO_ISSUER=https://sso.domainanda.com
OFFICE_BOOTSTRAP_ADMIN_SSO_SUBJECT=subject_uuid_admin_sso

CACHE_STORE=database
```

Simpan file `.env` (`Ctrl + O`, lalu `Enter`, lalu `Ctrl + X`).

Kunci file `.env` agar hanya dapat dibaca oleh user yang berhak:
```bash
chmod 600 .env
```

---

## 9. Langkah 8: Install Dependensi, Migrasi, & Build Asset

Jalankan perintah instalasi Composer tanpa dependensi development:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Generate Application Encryption Key:
```bash
php artisan key:generate --force
```

Buat symbolic link public storage (PENTING untuk logo, stamp perusahaan, dan tanda tangan):
```bash
php artisan storage:link
```

Jalankan migrasi database:
```bash
php artisan migrate --force
```

Jalankan seeding awal role dan permission (hanya saat instalasi pertama):
```bash
php artisan db:seed --class=RolePermissionSeeder --force
```

Build asset CSS & JS untuk production menggunakan Vite:
```bash
npm ci
npm run build
```

---

## 10. Langkah 9: Konfigurasi Nginx & SSL (Certbot Let's Encrypt)

Pasang Nginx dan Certbot:
```bash
sudo apt install -y nginx certbot python3-certbot-nginx
```

Buat file konfigurasi server block Nginx di `/etc/nginx/sites-available/office.conf`:

```bash
sudo nano /etc/nginx/sites-available/office.conf
```

Isi dengan konfigurasi berikut:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name office.domainanda.com;

    # Redirect seluruh HTTP traffic ke HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name office.domainanda.com;

    root /var/www/office/public;
    index index.php index.html;

    # Batas ukuran upload foto stamp & tanda tangan
    client_max_body_size 20M;

    # Security Headers (Wajib untuk lulus Smoke Check)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Cache static assets dari Vite build
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform";
        try_files $uri =404;
    }

    # Public storage files (stamps, signatures, logos)
    location /storage/ {
        alias /var/www/office/storage/app/public/;
        try_files $uri =404;
        access_log off;
        log_not_found off;
    }

    # Blokir akses langsung ke file tersembunyi (.env, .git, dll)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Blokir akses ke direktori private
    location ~ /(storage/app/private|bootstrap/cache) {
        deny all;
        return 404;
    }

    # FastCGI PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 180;
    }
}
```

Aktifkan konfigurasi dan test:
```bash
sudo ln -s /etc/nginx/sites-available/office.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Pasang sertifikat SSL gratis via Let's Encrypt Certbot:
```bash
sudo certbot --nginx -d office.domainanda.com
```
Certbot akan memperbarui file konfigurasi Nginx secara otomatis dengan parameter sertifikat SSL yang valid.

---

## 11. Langkah 10: Konfigurasi Queue Worker dengan Supervisor

Sistem JBLU Office membutuhkan **dua worker** terpisah:
1. `office-worker-pdf`: Khusus menangani antrean pembuatan file PDF (`--queue=pdf`) dengan timeout 150 detik.
2. `office-worker-default`: Menangani antrean sistem umum (`--queue=default`).

Buat file konfigurasi Supervisor:
```bash
sudo nano /etc/supervisor/conf.d/office-workers.conf
```

Isi konfigurasi berikut:

```ini
[program:office-worker-pdf]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/office/artisan queue:work database --queue=pdf --tries=3 --timeout=150 --backoff=10
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/office-worker-pdf.log
stopwaitsecs=160

[program:office-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/office/artisan queue:work database --queue=default --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/office-worker-default.log
stopwaitsecs=70
```

Aktifkan dan jalankan supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

Output status akan menunjukkan worker dalam keadaan `RUNNING`:
```text
office-worker-default:office-worker-default_00   RUNNING   pid 1234, uptime 0:00:10
office-worker-pdf:office-worker-pdf_00           RUNNING   pid 1235, uptime 0:00:10
office-worker-pdf:office-worker-pdf_01           RUNNING   pid 1236, uptime 0:00:10
```

---

## 12. Langkah 11: Konfigurasi Laravel Task Scheduler (Cron)

Laravel scheduler menjalankan pembersihan file temporary, pemeriksaan token expired, dan rutinitas background secara berkala.

Tambahkan cron job untuk user `www-data`:
```bash
sudo crontab -u www-data -e
```

Tambahkan baris berikut di bagian paling bawah:
```cron
* * * * * cd /var/www/office && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 13. Langkah 12: Optimasi Cache & Uji Validasi Release Gate

Jalankan perintah cache untuk performa maksimal pada production:

```bash
cd /var/www/office
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Jalankan Pengecekan Kesiapan Sistem

1. **Security Gate**:
   ```bash
   php artisan office:security:check --production
   ```
   *Memastikan konfigurasi HTTPS, session secure cookie, secret key, dan proteksi disk private telah valid.*

2. **Operations Gate**:
   ```bash
   php artisan office:operations:check
   ```
   *Memastikan koneksi database, antrean tabel jobs, dan disk private storage writable.*

3. **Smoke Test HTTP**:
   ```bash
   php artisan office:smoke --url=https://office.domainanda.com
   ```
   *Memverifikasi endpoint `/health/live`, `/health/ready`, redirect `/auth/login`, serta HTTP headers HSTS, nosniff, dan DENY.*

---

## 14. Prosedur Pembaruan / Update Aplikasi (Maintenance Workflow)

Saat ada rilis atau pembaruan kode di masa mendatang, ikuti urutan berikut untuk meminimalkan downtime:

```bash
cd /var/www/office

# 1. Aktifkan maintenance mode dengan bypass secret
MAINTENANCE_SECRET=$(openssl rand -hex 16)
php artisan down --retry=60 --refresh=15 --secret="$MAINTENANCE_SECRET"
echo "Bypass secret: $MAINTENANCE_SECRET"

# 2. Tarik kode terbaru
git pull origin main

# 3. Update dependensi
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# 4. Jalankan migrasi database
php artisan migrate --force

# 5. Refresh cache sistem
php artisan optimize:clear
php artisan optimize

# 6. Restart queue workers (agar worker memuat kode PHP terbaru)
php artisan queue:restart
sudo supervisorctl restart all
sudo systemctl restart php8.2-fpm

# 7. Matikan maintenance mode
php artisan up

# 8. Verifikasi smoke test pasca rilis
php artisan office:smoke --url=https://office.domainanda.com
```

---

## 15. Troubleshooting & FAQ

### A. Dokumen PDF Terus Berstatus `Queued` dan Tidak Selesai
1. Periksa apakah Supervisor worker berjalan:
   ```bash
   sudo supervisorctl status office-worker-pdf:*
   ```
2. Cek log worker PDF:
   ```bash
   tail -n 50 /var/log/supervisor/office-worker-pdf.log
   ```
3. Cek apakah binary Chromium dapat dieksekusi:
   ```bash
   /usr/bin/chromium-browser --version
   ```
4. Cek permission direktori penyimpanan temporary dan documents:
   ```bash
   ls -la /var/www/office/storage/app/private/
   ```

### B. Foto Tanda Tangan atau Stamp Tidak Muncul di Tampilan
1. Pastikan symbolic link public telah terbuat:
   ```bash
   ls -l /var/www/office/public/storage
   # Harus menunjuk ke /var/www/office/storage/app/public
   ```
   Jika belum, jalankan `php artisan storage:link`.
2. Pastikan file tersimpan di `/var/www/office/storage/app/public/user-signatures/` atau `company-stamps/` dan memiliki izin baca oleh `www-data`.

### C. Error 500 / Log Laravel
Jika terjadi kendala pada aplikasi, periksa log harian Laravel:
```bash
tail -n 100 -f /var/www/office/storage/logs/laravel-$(date +%Y-%m-%d).log
```

---

## 16. Strategi Backup Harian (RPO & RTO)

Sesuai dokumen operasional:
1. **Database PostgreSQL**:
   Jadwalkan backup berkala via cron:
   ```bash
   pg_dump -U office_user -h 127.0.0.1 office | gzip > /backup/office_db_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
   ```
2. **File Private & Uploads**:
   Cadangkan folder `storage/app/private` dan `storage/app/public` ke penyimpanan sekunder (off-host / cloud object storage).
