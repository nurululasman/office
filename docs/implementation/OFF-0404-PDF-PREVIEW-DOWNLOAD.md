# OFF-0404 - Preview dan download PDF privat

## Endpoint

PDF resmi dilayani melalui dua endpoint terautentikasi:

| Method | Route | Disposition |
| --- | --- | --- |
| `GET` | `/quotations/{quotation}/pdf/preview` | `inline` |
| `GET` | `/quotations/{quotation}/pdf/download` | `attachment` |

Kedua endpoint membaca file melalui Laravel Storage dari disk yang tercatat pada `generated_files`. Path storage maupun URL publik tidak pernah diberikan kepada browser.

## Policy dan status

`QuotationPolicy::viewPdf` mewajibkan permission `quotations.pdf.read`. Authorization dilakukan sebelum metadata atau kondisi file diperiksa, sehingga pengguna tanpa izin tidak dapat mengetahui apakah PDF ada, sedang diproses, atau gagal.

Respons operasional:

- record tidak ada: `404`;
- status `queued`/`processing`: `409`;
- status `failed`: `503` tanpa menampilkan `last_error` internal;
- status `ready` tetapi object storage hilang: `404`;
- status `ready` dan object ada: `200 application/pdf`.

Respons PDF memakai `Cache-Control: private, no-store, max-age=0`, `X-Content-Type-Options: nosniff`, dan ETag dari SHA-256. Halaman detail quotation menampilkan status generation serta tombol preview/download hanya ketika file `ready` dan pengguna memiliki policy.

## Nama file stabil

Nama browser dibentuk hanya dari nomor dokumen resmi:

```text
Quotation-{document_number}.pdf
```

Karakter di luar `A-Z`, `a-z`, angka, titik, underscore, dan hyphen diganti dengan hyphen. Untuk bukti nomor `QT-JBLU-2026070001`, kedua endpoint konsisten menghasilkan:

```text
Quotation-QT-JBLU-2026070001.pdf
```

Fallback UUID quotation tersedia untuk melindungi kontrak filename bila data nomor historis tidak valid, walaupun renderer OFF-0403 hanya membuat PDF untuk quotation complete yang telah bernomor.

## Verifikasi

```powershell
php artisan view:cache
php artisan view:clear
php artisan test tests/Feature/QuotationPdfGenerationTest.php
php artisan test
```

Hasil 18 Juli 2026:

- endpoint/status/policy suite: **6 passed, 37 assertions, 1 skipped**; skip adalah gate Chrome nyata OFF-0403 yang telah lulus melalui eksekusi opt-in;
- full default suite: **97 passed, 565 assertions, 6 skipped**;
- enam skip terdiri dari satu gate Chrome yang telah lulus dan lima gate PostgreSQL yang telah lulus melalui runner `OFF-0306`;
- Pint, kompilasi Blade, dan pemeriksaan whitespace lulus.
