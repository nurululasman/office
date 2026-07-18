# OFF-0302 — Quotation draft management

## Scope

Step ini menyediakan alur operator untuk:

- daftar quotation dengan pencarian, filter status, dan pagination;
- pembuatan serta perubahan draft;
- detail customer, sender, item dinamis, terms, status, dan nomor dokumen;
- pemilihan template quotation aktif dan penyalinan definisi kolom sebagai snapshot;
- authorization `quotations.read`, `quotations.create`, `quotations.update-own`, dan `quotations.update-any`;
- audit `quotation.created` dan `quotation.updated`.

## Validasi item dinamis

Backend membangun kontrak validasi dari snapshot `settings.columns`:

- hanya key yang didefinisikan template yang diterima;
- kolom `required` wajib memiliki nilai;
- `text`, `decimal`, `integer`, `date`, `boolean`, dan `currency` divalidasi menurut `value_type`;
- angka desimal/currency memakai string desimal bertitik dan tidak dikonversi ke floating point;
- item dan terms disimpan dengan posisi deterministik;
- template yang berubah atau dinonaktifkan tidak mengubah schema draft yang sudah dibuat.

Draft yang bukan milik user hanya dapat diedit dengan `quotations.update-any`. Status selain `draft` tidak dapat diedit melalui endpoint ini.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationSchemaTest.php tests/Feature/QuotationDraftManagementTest.php
php artisan view:cache
php artisan view:clear
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **10 passed, 49 assertions**;
- Blade compilation: **lulus**;
- full suite: **65 passed, 333 assertions, 4 skipped**;
- test yang dilewati adalah gate concurrency PostgreSQL Fase 2 yang memerlukan `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`.
