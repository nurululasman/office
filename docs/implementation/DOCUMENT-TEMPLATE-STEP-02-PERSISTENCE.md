# Document Template Step 02 — Persistence dan Model Versioning

## Scope

Step ini menerapkan fondasi persistence dari kontrak Step 1 tanpa mengambil
scope permission, CRUD, TinyMCE, sanitizer, placeholder parser, atau renderer.

Perubahan meliputi:

- identitas keluarga template melalui `template_key`;
- versi unik per keluarga template;
- lifecycle persistence `draft`, `active`, dan `archived`;
- satu versi aktif per keluarga template;
- HTML, checksum, item schema, defaults, editor config, optimistic lock, serta
  metadata actor/activation;
- snapshot kontrak render dan company profile pada quotation ketika draft
  pertama dibuat;
- backfill kompatibel untuk template dan quotation existing.

## Kontrak Database

### `document_templates`

Kolom baru:

- `template_key`;
- `status`;
- `content_html` dan `content_sha256`;
- `item_schema`;
- `default_intro_text` dan `default_closing_text`;
- `default_terms`;
- `editor_config`;
- `lock_version`;
- `created_by`, `updated_by`, dan `activated_by`;
- `activated_at`.

Constraint utama:

- unique `(type, template_key, version)`;
- partial unique `(type, template_key) WHERE status = 'active'`;
- PostgreSQL check constraint untuk status dan format kebab-case
  `template_key`.

Kolom legacy `settings` dan `is_active` dipertahankan sementara agar alur
quotation, initial-data manifest, dan deployment existing tetap kompatibel.
Model menjaga `settings`/`item_schema` serta `is_active`/`status` tetap selaras
untuk operasi yang melalui Eloquent.

### `quotations`

Kolom baru:

- `template_snapshot` JSON;
- `template_content_sha256`;
- `placeholder_contract_version`.

Snapshot dibuat otomatis oleh model pada transaksi pembuatan quotation jika
caller belum menyediakan snapshot. Snapshot berisi identitas dan versi template,
HTML, item schema, serta company profile yang diperlukan renderer.

Preview/PDF belum dipindahkan untuk membaca snapshot pada Step 2; perubahan
renderer tetap menjadi scope Step 7–9.

## Backfill

Migration memetakan data existing sebagai berikut:

- seluruh versi legacy pada satu `type` masuk ke keluarga `{type}-default`;
- versi tertinggi yang memiliki `is_active=true` menjadi `status=active`;
- versi legacy lainnya menjadi `status=archived`, sehingga data lama dengan
  beberapa versi aktif tetap dapat menerima partial unique index;
- `settings` disalin ke `item_schema`;
- HTML kompatibilitas awal dan checksum dibuat deterministik;
- quotation existing memperoleh snapshot dari template dan company profile yang
  direferensikan.

Backfill tidak mengubah item, terms, status workflow, document number, generated
file, atau sequence.

## Compatibility Boundary

Pada Step 2:

- template baru yang dibuat oleh alur legacy tetap dianggap aktif;
- lifecycle transition service dan optimistic-lock enforcement belum dibuat;
- template aktif belum dibuat immutable pada model karena initial-data command
  existing masih melakukan `updateOrCreate`;
- enforcement aksi create/update/activate/archive dilakukan pada Step 3–4;
- sanitasi dan validasi placeholder dilakukan pada Step 6.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationSchemaTest.php
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationWorkflowTest.php tests/Feature/QuotationPdfGenerationTest.php tests/Feature/InitialDataCutoverTest.php
php artisan test
php vendor/bin/pint --test
```

PostgreSQL partial unique dan row locking tetap memerlukan gate PostgreSQL.
Kelulusan SQLite tidak dianggap sebagai bukti concurrency PostgreSQL.

## Hasil Verifikasi 23 Juli 2026

```text
QuotationSchemaTest + InitialDataCutoverTest:
11 passed, 44 assertions

Pint (file Step 2):
passed

Full default suite:
122 passed, 673 assertions, 6 skipped, 3 failed
```

Tiga kegagalan full suite tidak berasal dari perubahan Step 2:

1. `ExampleTest` masih mengharapkan root `/` mengembalikan `200`, sedangkan
   perilaku aplikasi saat ini mengembalikan redirect `302`.
2. `SecurityHardeningTest` memiliki expectation root `200` yang sama.
3. `QuotationDraftManagementTest` mengharapkan padding tabel
   `0 2mm 1.8mm`, sedangkan Blade existing menggunakan `0 2mm 0`.

Step 2 tidak mengubah root routing atau CSS quotation. Real Chrome PDF dan gate
PostgreSQL tidak dijalankan pada step ini.
