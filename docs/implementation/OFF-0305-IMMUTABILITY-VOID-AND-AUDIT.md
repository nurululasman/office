# OFF-0305 — Immutability, void, and audit trail

## Scope

- Policy edit hanya untuk status `draft` dan `rejected`; pending approval, complete, serta void tidak dapat diedit.
- Model boundary menolak perubahan konten dan penghapusan quotation complete/void.
- Revisi quotation rejected mengembalikan status ke draft dan mencatat `quotation.revised` tanpa menghapus jejak rejection.
- Void quotation complete dengan alasan, permission `quotations.void`, optimistic version check, dan row lock.
- Void nomor dokumen terkait dalam transaksi yang sama tanpa menghapus register atau mengembalikan sequence.
- Audit trail quotation pada halaman detail, termasuk actor, waktu, action, before/after, dan context.

## Invariant void

Void hanya berlaku untuk quotation `complete` yang sudah mempunyai nomor resmi. Dalam satu transaksi sistem:

1. mengunci quotation;
2. memverifikasi status dan `lock_version`;
3. mengunci dan me-void nomor dokumen melalui `DocumentVoidService`;
4. mengubah status quotation menjadi `void`;
5. mencatat `document.voided` dan `quotation.voided` dengan alasan serta actor.

Retry void bersifat idempotent. Nomor, sequence, waktu void pertama, alasan pertama, dan audit sukses pertama tidak berubah.

## Immutability

- Endpoint update ditolak untuk pending approval, complete, dan void.
- Mutation konten Eloquent pada quotation complete/void melempar exception.
- Quotation complete/void tidak dapat dihapus.
- Satu-satunya perubahan yang diizinkan setelah complete adalah transition terkontrol menuju `void` beserta increment `lock_version`.
- Generated file dan metadata PDF berada pada tabel terpisah sehingga tidak memerlukan mutation snapshot quotation complete.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationWorkflowTest.php
php artisan view:cache
php artisan view:clear
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **16 passed, 118 assertions**;
- Blade compilation: **lulus**;
- full suite: **84 passed, 424 assertions, 4 skipped**;
- empat test yang dilewati adalah gate concurrency PostgreSQL Fase 2 yang memerlukan `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`.
