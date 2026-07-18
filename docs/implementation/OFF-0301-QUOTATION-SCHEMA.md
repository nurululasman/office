# OFF-0301 — Quotation schema foundation

## Scope

Step ini menambahkan fondasi persistence untuk quotation tanpa mengambil scope form, workflow completion, atau PDF:

- aggregate `quotations`, `quotation_items`, `quotation_item_values`, dan `quotation_terms`;
- profil perusahaan serta `document_templates` berversi;
- metadata `generated_files` yang polymorphic dan terhubung ke versi template;
- model Eloquent, cast, relasi, urutan stabil, UUID, foreign key, serta unique constraint.

## Kontrak data penting

- Quotation baru berstatus `draft`, belum memiliki `document_id`, dan menyimpan creator, referensi versi template, snapshot `approval_mode`, serta `item_schema`.
- Nilai item tetap berupa string dengan `value_type`; schema tidak memiliki kolom khusus `rate_20` atau `rate_40`.
- Key dan posisi nilai unik per item; posisi item dan term unik per quotation.
- Template unik per kombinasi `type` dan `version`.
- File hasil render menyimpan disk/path, MIME, size, SHA-256, waktu/aktor generator, owner polymorphic, dan template yang digunakan.
- Child item, value, dan term ikut terhapus bila draft quotation dihapus. Document/template yang sudah direferensikan tidak dapat dihapus lewat foreign key.
- PostgreSQL menegakkan enum status/mode/value type serta pola key `snake_case` melalui check constraint.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationSchemaTest.php
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **4 passed, 13 assertions**;
- full suite: **59 passed, 297 assertions, 4 skipped**;
- empat test yang dilewati adalah gate concurrency PostgreSQL Fase 2 dan memerlukan `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`; bukan kegagalan OFF-0301.
