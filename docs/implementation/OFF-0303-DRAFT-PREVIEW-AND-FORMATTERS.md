# OFF-0303 — Draft preview and formatters

## Scope

- Endpoint `GET /quotations/{quotation}/preview` dengan policy `quotations.pdf.read`.
- Preview HTML A4 portrait yang siap dicetak, selalu menampilkan watermark `DRAFT` dan pemberitahuan bahwa preview bukan dokumen resmi.
- Metadata quotation, customer, sender, tabel dari snapshot kolom dinamis, terms, penutup, dan ruang tanda tangan.
- Tombol preview dari halaman detail quotation.
- Formatter terpusat untuk `text`, `decimal`, `integer`, `date`, `boolean`, dan `currency`.

## Aturan formatting

- Tanggal memakai nama bulan Indonesia, misalnya `18 Juli 2026`.
- IDR memakai awalan `Rp`, separator ribuan titik, tanpa digit desimal, dan pembulatan half-up.
- Decimal non-currency memakai separator desimal koma.
- Formatter memproses string secara langsung tanpa konversi float, termasuk nilai yang melampaui batas integer aman.
- Nilai kosong ditampilkan sebagai em dash dan boolean ditampilkan sebagai `Ya`/`Tidak`.

Preview menggunakan snapshot `item_schema` dan nilai draft; endpoint tidak menerbitkan nomor, membuat file final, atau mengubah quotation.

## Verifikasi

```powershell
php artisan test tests/Unit/Quotations/QuotationValueFormatterTest.php tests/Feature/QuotationDraftManagementTest.php
php artisan view:cache
php artisan view:clear
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **16 passed, 53 assertions**;
- Blade compilation: **lulus**;
- full suite: **75 passed, 350 assertions, 4 skipped**;
- empat test yang dilewati adalah gate concurrency PostgreSQL Fase 2 yang memerlukan `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`.
