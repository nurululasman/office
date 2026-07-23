# Step 10 — Preview dan PDF Berbasis Template Renderer

Tanggal implementasi: 23 Juli 2026.

## Arsitektur Rendering

Preview browser dan PDF resmi sekarang memakai rantai yang sama:

```text
Quotation snapshot
  -> QuotationTemplateRenderer
  -> QuotationDocumentRenderer
  -> quotations.document shell
  -> browser preview atau Chrome PDF
```

`QuotationTemplateRenderer` menangani HTML template, placeholder, escaping,
table/list/sub-list, terms, signature, watermark, dan snapshot validation.
`QuotationDocumentRenderer` menyediakan sumber logo embedded yang sama.
`quotations.document` hanya menjadi shell dokumen.

## Tanggung Jawab Shell

Shell menangani:

- halaman A4 portrait dan margin print;
- font, warna branding snapshot, dan area konten;
- toolbar khusus browser preview;
- CSS tabel, list, nested list, signature, dan watermark;
- repeated table header serta aturan page-break;
- footer draft atau nomor quotation resmi.

HTML template dimasukkan sebagai hasil renderer tersanitasi, bukan input request
mentah.

## Konsistensi Preview dan PDF

- Controller preview memanggil `QuotationDocumentRenderer::content()` dengan
  mode draft.
- `QuotationPdfRenderer` memanggil layanan yang sama dengan mode official.
- Keduanya membungkus hasil melalui view `quotations.document`.
- PDF official tetap hanya dapat dibuat untuk quotation `complete` yang sudah
  memiliki nomor.
- Checksum template dan checksum logo snapshot gagal secara tertutup bila tidak
  cocok.

Perbedaan yang disengaja hanya mode output: preview memiliki toolbar,
watermark/teks draft sesuai template, sedangkan PDF official memakai nomor
document dan tidak memiliki toolbar draft.

## Logo dan Branding

Renderer mencoba logo pada `company_profile.logo_path` snapshot. Bila tidak
tersedia, logo JBLU existing digunakan sebagai fallback kompatibilitas.
PNG/JPEG diubah menjadi data URI agar browser preview dan proses Chrome tidak
bergantung pada HTTP eksternal. Jika snapshot mempunyai `logo_sha256`, isi file
wajib cocok.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationTemplateRendererTest.php tests/Feature/QuotationPdfGenerationTest.php
php artisan view:cache
php artisan view:clear
php vendor/bin/pint --test app/Http/Controllers/QuotationController.php app/Services/Quotations/QuotationDocumentRenderer.php app/Services/Quotations/QuotationPdfRenderer.php tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationPdfGenerationTest.php
php artisan test --compact
```

Hasil terfokus 23 Juli 2026:

```text
27 passed, 162 assertions, 1 skipped

Blade compilation, Pint, PHP syntax, dan diff check:
passed

Full default suite:
150 passed, 864 assertions, 6 skipped, 2 existing failures
```

Kedua kegagalan existing berasal dari expectation root `/` berstatus `200`
versus redirect aplikasi existing `302`. Assertion CSS quotation lama yang
sebelumnya gagal sudah lulus melalui shell Step 10.

Tes real Chrome tetap opt-in melalui `OFFICE_RUN_PDF_RENDERER_TESTS=true`.
Visual QA untuk konten pendek, panjang, tabel besar, dan multi-page merupakan
cakupan Step 11. PostgreSQL, UAT, dan deployment belum dilakukan.
