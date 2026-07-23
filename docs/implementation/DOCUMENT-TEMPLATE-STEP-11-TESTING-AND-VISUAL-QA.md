# Step 11 — Automated Testing dan Visual PDF QA

Tanggal verifikasi: 23 Juli 2026.

## Automated Contract Coverage

Suite Steps 3–10 telah mencakup kontrak minimum:

- CRUD, policy, permission, dan rate limit template;
- versioning, aktivasi, archive, dan audit;
- sanitasi HTML serta penolakan placeholder tidak dikenal;
- escaping scalar, item, dan terms;
- snapshot HTML, schema, versi, checksum, dan branding;
- perubahan template baru tidak mengubah quotation lama;
- table, list, nested list, depth overflow, dan cycle;
- default template pada form quotation;
- konsistensi template renderer untuk browser preview dan PDF official;
- queue, idempotency, failure state, private download, dan metadata PDF.

Verifikasi terfokus:

```text
42 passed, 285 assertions, 1 skipped
```

Tes Chrome yang biasanya opt-in juga dijalankan eksplisit:

```powershell
$env:OFFICE_RUN_PDF_RENDERER_TESTS='true'
php artisan test tests/Feature/QuotationPdfGenerationTest.php --filter=real_chrome_renderer
Remove-Item Env:OFFICE_RUN_PDF_RENDERER_TESTS
```

Hasil:

```text
1 passed, 5 assertions
```

## Visual Fixture

`scripts/render_off0401_fixture.php` sekarang melewati jalur production:

```text
sanitizer -> template snapshot -> QuotationTemplateRenderer
-> quotations.document shell -> Chrome PDF
```

Empat bukti disimpan di `output/pdf`:

| Fixture | Halaman | Fokus |
|---|---:|---|
| short | 1 | layout pendek, metadata, signature, footer |
| long-text | 2 | wrapping subject, address, intro, closing, dan cell panjang |
| table-large | 3 | 42 item, repeated table header, completion block |
| multi-page | 7 | 78 item, 10 terms, repeated header, transisi halaman |

Semua PDF berukuran A4 portrait.

## Visual Inspection

PDF dirender ulang menggunakan Poppler bundled pada 110 DPI dan seluruh 13
halaman diperiksa sebagai PNG/contact sheet.

Hasil akhir:

- tidak ada teks atau tabel terpotong;
- tidak ada baris item yang pecah di tengah halaman;
- header tabel berulang pada halaman lanjutan;
- nilai currency tetap sejajar dan terbaca;
- logo tajam serta tidak terdistorsi;
- metadata dan long text membungkus tanpa overflow;
- terms dapat berlanjut ke halaman berikutnya;
- signature block tidak terpecah;
- footer hanya tampil pada halaman terakhir dan tidak menimpa tabel;
- tidak ada placeholder mentah, black square, atau elemen tidak terbaca.

Temuan iterasi visual yang diperbaiki:

- footer fixed sebelumnya menimpa baris terakhir tabel;
- penempatan footer di area margin menghasilkan halaman ekstra/tidak stabil;
- signature spacing terlalu besar;
- metadata fixture dengan empat kolom membuat label terpecah.

Shell akhir memakai footer normal-flow pada halaman terakhir, signature flex
yang lebih ringkas, dan metadata fixture dua kolom.

## Regression

```text
Full default suite:
150 passed, 864 assertions, 6 skipped, 2 existing failures

Blade compilation dan Pint:
passed
```

Dua kegagalan existing tetap berasal dari expectation root `/` berstatus `200`
versus redirect aplikasi `302`. PostgreSQL, UAT, dan deployment belum dilakukan.
