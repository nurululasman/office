# OFF-0403 - Chrome PDF renderer dan queue

## Alur implementasi

Completion direct maupun maker-checker membuat satu record `generated_files` di dalam transaksi yang sama dengan penerbitan nomor. `GenerateQuotationPdf` kemudian didispatch dengan `afterCommit()` melalui koneksi database ke queue khusus `pdf`. Jika transaksi completion rollback, job tidak pernah tersedia bagi worker.

Worker menjalankan `QuotationPdfRenderer` dengan aturan berikut:

- hanya menerima quotation berstatus `complete` yang sudah memiliki nomor dokumen;
- memuat item, nilai key-value, terms, template, company profile, dan nomor dari aggregate tersimpan;
- merender Blade yang sama dengan preview, tetapi tanpa watermark draft;
- menyematkan logo lokal sebagai data URI sehingga render tidak membutuhkan HTTP atau internet;
- menjalankan Chrome headless dengan profile sementara privat dan tanpa header/footer bawaan browser;
- menulis ke temporary object lalu memindahkannya ke path final privat yang stabil;
- menyimpan MIME type, byte size, waktu generate, dan SHA-256 dari byte final.

Path final adalah `quotations/{quotation_uuid}/quotation.pdf` pada disk `documents`, yaitu `storage/app/private/documents` secara default. File tidak ditempatkan pada disk public dan tidak membutuhkan symbolic link.

## Idempotensi, status, dan retry

Satu quotation/kind/path hanya memiliki satu record. Status berjalan melalui `queued`, `processing`, `ready`, atau `failed`. Job `ready` adalah no-op sehingga retry atau delivery ganda tidak membuat file final kedua.

Job memiliki tiga attempt dengan backoff 10, 30, dan 90 detik. Setiap attempt menambah counter dan menyimpan `started_at`. Exception Chrome/storage disimpan secara terbatas pada `last_error`, status menjadi `failed`, lalu exception dilempar ulang agar mekanisme retry Laravel tetap bekerja.

Konfigurasi:

```dotenv
QUEUE_CONNECTION=database
QUEUE_AFTER_COMMIT=true
PDF_QUEUE_CONNECTION=database
PDF_QUEUE=pdf
OFFICE_DOCUMENT_DISK=documents
OFFICE_CHROME_BINARY=
OFFICE_PDF_TIMEOUT=120
```

`OFFICE_CHROME_BINARY` boleh kosong pada host yang memakai lokasi Chrome/Chromium standar. Di production sebaiknya diisi eksplisit.

Worker production:

```powershell
php artisan queue:work database --queue=pdf --tries=3 --timeout=150 --backoff=10
```

## Gate Chrome nyata

Gate opt-in menjalankan renderer sesungguhnya, memverifikasi signature `%PDF-`, status, ukuran, SHA-256, dan waktu generate:

```powershell
$env:OFFICE_RUN_PDF_RENDERER_TESTS='true'
php artisan test tests/Feature/QuotationPdfGenerationTest.php --filter=real_chrome_renderer
```

Hasil 18 Juli 2026:

- gate Chrome: **1 passed, 5 assertions**;
- output: `output/pdf/OFF-0403-renderer-proof.pdf`;
- 1 halaman A4 `594.96 x 841.92 pt`;
- SHA-256: `E3206A7D0757A6D5A58A7145C710CFBB6587DEA26B3D150B5F71BDE9F07A62CF`;
- pemeriksaan PNG menunjukkan nomor resmi, logo, tabel, penutup, tanda tangan, dan footer tampil tanpa watermark draft, clipping, atau overlap.

## Verifikasi otomatis

```powershell
php artisan view:cache
php artisan view:clear
php artisan test tests/Feature/QuotationPdfGenerationTest.php tests/Feature/QuotationWorkflowTest.php tests/Feature/FoundationConfigurationTest.php
php artisan test
```

Hasil:

- targeted: **17 passed, 178 assertions, 1 skipped** karena gate Chrome bersifat opt-in;
- full default suite: **94 passed, 548 assertions, 6 skipped**;
- enam skip terdiri dari satu gate Chrome yang lulus pada eksekusi opt-in dan lima gate PostgreSQL yang sebelumnya telah lulus melalui runner `OFF-0306`;
- Pint, kompilasi Blade, dan pemeriksaan whitespace lulus.
