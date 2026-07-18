# OFF-0201 - Document Registry Schema

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- `document_types` memakai UUID, code unik, pola nomor, reset tahunan, mode approval, status aktif, dan timestamp timezone-aware.
- `document_sequences` memakai UUID dan foreign key terproteksi ke tipe dokumen, dengan unique key `(document_type_id, period_year)`.
- `documents` memakai UUID, menyimpan snapshot nomor dan sequence, issuer/void metadata, serta optional polymorphic source UUID.
- Unique constraints melindungi `(document_type_id, period_year, sequence_value)`, `(document_type_id, period_year, number)`, dan `(source_type, source_id)`.
- PostgreSQL check constraints membatasi reset period, approval mode, tahun/sequence, pasangan source, dan kelengkapan metadata void.
- `audit_logs.subject_id` memakai string agar audit yang menunjuk user numerik lama dan model domain UUID sama-sama valid.
- Model `DocumentType`, `DocumentSequence`, dan `Document` memakai UUID, casts, mass-assignment allowlist, dan relasi Eloquent.

## Bukti verifikasi

```text
php artisan test
29 passed (144 assertions)

vendor/bin/pint --test app/Models database/migrations tests/Feature/DocumentRegistrySchemaTest.php
PASS

php artisan migrate --force
2026_07_18_000004_create_document_registry_tables ... DONE
```

Migration dijalankan terhadap koneksi PostgreSQL lokal untuk membuktikan foreign key, unique index, perubahan tipe subject audit, dan check constraint dapat dibuat pada database target. `DocumentRegistrySchemaTest` membuktikan UUID/model relation serta penolakan duplicate sequence, duplicate number, dan duplicate source entity.
