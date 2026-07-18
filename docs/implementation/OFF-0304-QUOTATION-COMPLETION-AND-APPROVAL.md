# OFF-0304 — Quotation completion and approval

## Scope

- Direct completion untuk quotation dengan snapshot `approval_mode=direct`.
- Submit, approve, dan reject untuk workflow `maker_checker`.
- Penerbitan nomor resmi melalui `DocumentNumberIssuer` hanya ketika status berubah menjadi `complete`.
- UI aksi workflow pada detail quotation sesuai mode, status, dan permission.
- Optimistic locking melalui `lock_version` pada edit dan seluruh aksi workflow.
- Pessimistic row locking melalui `SELECT ... FOR UPDATE` pada transition workflow.

## Invariant transaksi

Direct completion dan maker-checker approval menjalankan langkah berikut dalam satu transaksi database:

1. lock quotation terbaru;
2. periksa status, mode, actor, dan `lock_version`;
3. lock tipe/sequence dokumen dan terbitkan nomor idempotent berdasarkan source quotation;
4. hubungkan `document_id`, ubah status menjadi `complete`, dan simpan actor/waktu completion;
5. simpan audit workflow dan nomor yang diterbitkan;
6. commit seluruh perubahan atau rollback seluruhnya.

Retry completion yang sudah sukses mengembalikan quotation complete tanpa membuat nomor atau audit sukses kedua. Unique source constraint pada register tetap menjadi pertahanan database terhadap nomor ganda.

## Audit dan maker-checker

- Direct mode mencatat `quotation.approval_bypassed` dengan alasan `approval_mode_direct`; field approval tetap null.
- Maker-checker mencatat `quotation.submitted`, `quotation.approved`, atau `quotation.rejected`.
- Kedua jalur completion mencatat `quotation.completed` beserta nomor dan jalur completion.
- Creator atau submitter tidak boleh menjadi checker, termasuk ketika actor memiliki role `system-admin`.
- Draft/pending approval belum mempunyai nomor; rejection juga tidak menerbitkan nomor.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationWorkflowTest.php
php artisan view:cache
php artisan view:clear
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **12 passed, 89 assertions**;
- Blade compilation: **lulus**;
- full suite: **80 passed, 395 assertions, 4 skipped**;
- empat test yang dilewati adalah gate concurrency PostgreSQL Fase 2 yang memerlukan `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`.
