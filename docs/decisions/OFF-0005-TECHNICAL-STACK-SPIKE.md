# OFF-0005 - Keputusan Stack Teknis dan Spike

- Status: Accepted
- Tanggal: 2026-07-18

## Ringkasan keputusan

| Area | Keputusan awal |
|---|---|
| Database | PostgreSQL 16+ untuk dev, CI integration, staging, dan production |
| UI | Laravel Blade + Tabler + Alpine.js, asset aplikasi melalui Vite |
| OAuth client | `league/oauth2-client` `GenericProvider` dengan adapter profile SSO JBLU |
| PDF | `spatie/laravel-pdf` v2, driver `chrome` (`chrome-php/chrome`) |
| Queue | Laravel database queue, queue terpisah bernama `pdf` |
| Storage | Laravel private local disk untuk MVP; backup di luar host wajib |

Keputusan ini mengutamakan sedikit komponen operasional untuk organisasi kecil, tetapi seluruh dependency dibungkus interface Laravel/domain agar dapat diganti ketika skala atau topologi deployment berubah.

## Bukti runtime repository

Pemeriksaan checkout Office menghasilkan:

- Laravel 12 dengan PHP 8.2.12;
- extension `PDO`, `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, dan `gd` tersedia;
- konfigurasi lokal saat ini masih SQLite dan akan dimigrasikan pada `OFF-0101`;
- `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=local`, database session/cache sudah menjadi default skeleton;
- migrations `jobs`, `job_batches`, dan `failed_jobs` sudah tersedia;
- Node.js 20.19.5 tersedia;
- Google Chrome 124 dan Microsoft Edge 150 ditemukan pada workstation;
- layout Blade existing menggunakan Tabler CSS/JS;
- baseline `php artisan test`: **2 passed, 2 assertions**.

## Database: PostgreSQL 16+

PostgreSQL dipilih sebagai satu-satunya engine utama. SQLite hanya boleh dipakai untuk test unit yang tidak bergantung pada SQL/locking; seluruh integration test penomoran, approval concurrency, queue, dan migration harus berjalan pada PostgreSQL.

Alasan:

- `SELECT ... FOR UPDATE`, transaction, unique/check constraints, dan upsert cocok dengan mesin sequence;
- extension PHP sudah tersedia;
- menghindari perbedaan perilaku locking antara SQLite development dan production;
- PostgreSQL mendokumentasikan bahwa row lock menahan writer/locker lain sampai transaksi selesai, sesuai kebutuhan penerbitan nomor.

Konfigurasi target menggunakan `DB_CONNECTION=pgsql`. Versi minimum production adalah PostgreSQL 16; upgrade major mengikuti compatibility test dan backup/restore drill.

Referensi: [PostgreSQL concurrency control](https://www.postgresql.org/docs/current/mvcc.html) dan [Laravel database configuration](https://laravel.com/docs/12.x/database).

## UI: Blade + Tabler + Alpine.js

UI menggunakan server-rendered Blade, layout Tabler yang sudah ada, dan Alpine.js untuk interaksi lokal seperti:

- menambah/mengurutkan item quotation key-value;
- segment builder pola nomor;
- preview field dan modal konfirmasi;
- status polling ringan untuk job PDF.

Vite tetap menjadi bundler JavaScript/CSS aplikasi. React, Vue, Inertia, dan Livewire tidak dipakai pada MVP untuk menghindari dua model state/rendering. Tailwind yang ada pada skeleton tidak menjadi styling utama; Tabler adalah design system UI agar CSS tidak saling bertabrakan. API JSON tetap dapat ditambahkan untuk integrasi, bukan sebagai kebutuhan SPA.

Alpine.js belum terpasang saat spike dan ditambahkan pada `OFF-0101` bersama entrypoint Vite yang diuji build.

## OAuth: League GenericProvider

`league/oauth2-client` dipilih karena `GenericProvider` mendukung endpoint authorize/token/resource owner eksplisit dan Authorization Code dengan PKCE S256. Ini cocok dengan SSO JBLU yang belum mempunyai discovery OIDC.

Pembagian tanggung jawab:

- library menghasilkan authorization URL, `state`, verifier/challenge PKCE, dan melakukan token exchange/refresh;
- adapter `SsoIdentityProvider` mengonfigurasi endpoint dari environment;
- response profile SSO berbentuk `{user: ..., tenant_id: ...}`, sehingga Office memanggil `/api/v1/auth/me` melalui authenticated request dan memetakannya ke DTO sendiri, tidak bergantung pada resource owner shape generik;
- revoke tetap melalui `/oauth/revoke` dengan Laravel HTTP client bila API library tidak mengekspos kontrak internal tersebut;
- token disimpan encrypted server-side sesuai `OFF-0001`.

Spike algoritma menghasilkan verifier URL-safe sepanjang 86 karakter dan challenge S256 URL-safe sepanjang 43 karakter. Keduanya memenuhi batas PKCE yang digunakan SSO.

Package dipasang pada `OFF-0102`, bukan pada discovery ini, agar dependency masuk bersama implementation dan test kontraknya.

Referensi: [League OAuth2 Client basic usage and PKCE](https://oauth2-client.thephpleague.com/usage/).

## PDF: Spatie Laravel PDF Chrome driver

Dipilih `spatie/laravel-pdf` v2 dengan driver `chrome`, bukan Browsershot default.

Alasan:

- mendukung modern CSS untuk header, grid/flex, tabel, dan layout A4;
- driver Chrome memakai `chrome-php/chrome`, sehingga production tidak memerlukan Node.js/Puppeteer;
- paket mendukung Laravel 11+ dan PHP 8.2+;
- browser executable dapat dikonfigurasi eksplisit melalui environment;
- domain memakai interface `PdfRenderer`, sehingga driver dapat diganti ke Gotenberg/S3 topology kelak.

### Hasil spike visual

Chrome headless berhasil menghasilkan PDF:

- producer `Skia/PDF m124`;
- satu halaman A4 `594.96 x 841.92 pt`;
- logo `public/static/jblu.png` tampil dengan rasio benar;
- grid/flex, garis brand, tabel, dan ruang tanda tangan manual tampil tanpa clipping/overlap;
- render pertama memperlihatkan header/footer CLI default berupa tanggal, file URL, dan nomor halaman;
- render kedua menonaktifkan header/footer default dan lolos inspeksi visual.

Implementasi package wajib mengatur header/footer sendiri, tidak memuat asset dari network, menunggu readiness/file completion, dan tidak menggunakan `no_sandbox=true` pada production kecuali ada threat assessment serta isolasi container yang disetujui.

Package `spatie/laravel-pdf` dan `chrome-php/chrome` dipasang pada `OFF-0401`. Production image/server wajib menyediakan Chrome/Chromium kompatibel dan health check render.

Referensi: [Spatie Laravel PDF requirements](https://spatie.be/docs/laravel-pdf/v2/requirements), [Chrome driver configuration](https://spatie.be/docs/laravel-pdf/v2/drivers/using-the-chrome-driver), dan [PDF formatting](https://spatie.be/docs/laravel-pdf/v2/basic-usage/formatting-pdfs).

## Queue: database queue

Database queue dipilih untuk MVP karena volume dokumen rendah, migrations sudah tersedia, dan tidak menambah Redis sebagai komponen operasional. Gunakan:

- queue `default` untuk pekerjaan umum;
- queue `pdf` untuk rendering;
- dispatch PDF hanya setelah transaction commit;
- unique job berdasarkan owner/version;
- middleware without-overlapping untuk satu dokumen;
- retry terbatas, timeout lebih panjang dari browser timeout, serta failed job visibility;
- worker terpisah yang dikelola Supervisor/systemd.

Pindah ke Redis + Horizon dipertimbangkan bila antrean PDF rutin menunggu lebih dari target SLA, jumlah worker lintas host bertambah, atau kebutuhan monitoring/retry melampaui database queue. Migrasi queue tidak mengubah domain job.

Referensi: [Laravel queues](https://laravel.com/docs/12.x/queues).

## Storage: private local disk

PDF final, lampiran, dan artifact sensitif disimpan pada disk lokal private melalui Laravel filesystem. File tidak berada di `public/` dan hanya diunduh melalui controller berpolicy. Record `generated_files` menyimpan disk/path, size, MIME, SHA-256, template version, dan waktu render.

Logo JBLU saat ini tetap merupakan source-controlled public asset karena memang tampil pada dokumen/UI. PDF hasil render tidak public.

Syarat production local storage:

- satu application/worker node atau shared mount yang konsisten;
- backup encrypted di luar host;
- restore drill;
- directory permission least privilege;
- download tidak mengekspos real path.

Pindah ke S3-compatible private bucket wajib sebelum multi-node tanpa shared filesystem, kebutuhan durability lintas host, atau signed direct download. Laravel filesystem contract menjaga perubahan tersebut tetap configuration-driven.

Referensi: [Laravel 12 filesystem](https://laravel.com/docs/12.x/filesystem) dan [Laravel filesystem contract](https://api.laravel.com/docs/12.x/Illuminate/Contracts/Filesystem/Filesystem.html).

## Pilihan yang tidak digunakan pada MVP

| Alternatif | Alasan ditunda |
|---|---|
| MySQL/SQLite production | Menghindari matrix perilaku transaksi/locking; PostgreSQL menjadi satu target |
| React/Vue/Inertia | Tidak diperlukan untuk workflow form internal saat ini |
| Livewire | Menghindari model state kedua; Alpine cukup untuk interaksi lokal |
| Browsershot/Puppeteer | Membutuhkan Node/Puppeteer pada server; Chrome driver sudah memenuhi hasil visual |
| Dompdf | CSS/layout lebih terbatas untuk template dinamis dan header bertingkat |
| Redis/Horizon | Beban awal belum membenarkan komponen operasional tambahan |
| S3-compatible storage | Single-node private local disk cukup; migrasi disiapkan melalui filesystem contract |

## Acceptance criteria OFF-0005

- [x] PostgreSQL driver tersedia dan database target dipilih.
- [x] UI existing diperiksa dan Blade + Tabler + Alpine ditetapkan.
- [x] OAuth client dan pembagian adapter profile/revoke ditetapkan.
- [x] PKCE S256 diverifikasi dengan verifier/challenge URL-safe.
- [x] Chrome/Chromium tersedia dan PDF A4 berhasil dirender serta diperiksa visual.
- [x] Defect header/footer default ditemukan dan konfigurasi pencegahannya ditetapkan.
- [x] Database queue migrations tersedia dan topology queue ditetapkan.
- [x] Private storage serta trigger migrasi ke S3 ditetapkan.
- [x] Baseline test repository lulus.
