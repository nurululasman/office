# OFF-0405 - Visual QA matrix quotation PDF

## Metode

Lima fixture resmi dirender dari template produksi menggunakan Chrome headless tanpa header/footer browser. Setiap PDF diperiksa dengan Poppler untuk ukuran dan jumlah halaman, dirender ulang menjadi PNG 120 DPI, lalu seluruh 10 halaman diperiksa secara visual.

Fixture menggunakan nomor resmi `QT-JBLU-2026070001`, logo lokal, header bertingkat, general key-value columns, terms, completion block, tanda tangan, dan footer resmi. Tidak ada watermark draft.

## Matriks hasil

| Fixture | Cakupan | Halaman | SHA-256 |
| --- | --- | ---: | --- |
| `OFF-0405-short.pdf` | 2 item, layout normal | 1 | `BA61508E9A0343AC60E92CBBABB3065E325CA32E7346371B32A90CBEBBCB38B7` |
| `OFF-0405-long-text.pdf` | wrapping berat dalam description dan note | 2 | `6F14B5C35707D4FB1AA8DB8D7FD9C401C26600450C4097AF1C94058C7B612FC3` |
| `OFF-0405-special.pdf` | apostrophe, quote, ampersand, tag-like text, aksen, simbol, angka sangat besar, negatif | 1 | `7CD3FA1E4F2EA505F637C56E16993588591D9E6E1CDE0E6DC78E5795F6B96AF9` |
| `OFF-0405-empty.pdf` | null, string kosong, nol, dan baris seluruhnya kosong | 1 | `433F878B48BE9873553C9F1953E262E616445A37ED07E61BEA62C9E91E57C277` |
| `OFF-0405-many-items.pdf` | 75 item, baris pendek/panjang, 8 terms | 5 | `945CB065C32DCCC9093C98E8A2698784DECFDFFEF5E6128EE7245C6CD4A3C3D4` |

Semua halaman berukuran A4 portrait `594.96 x 841.92 pt`.

## Temuan dan perbaikan

QA nilai kosong menemukan formatter masih memakai em dash yang sebelumnya tampak mojibake pada sebagian output terminal/template. Placeholder dinormalisasi menjadi ASCII hyphen `-`, lalu unit test formatter diperbarui.

Fixture long-text awal terlalu tinggi dan menyebabkan ruang kosong besar sebelum tabel. Data dikalibrasi ulang tanpa mengurangi tujuan stress test; hasil akhir menjadi dua halaman dengan tabel/terms utuh pada halaman pertama dan completion block utuh pada halaman kedua.

## Checklist visual

- logo tajam, proporsional, dan tidak terpotong;
- nomor resmi, metadata, table header, terms, footer, dan signature blocks terbaca;
- header bertingkat berulang pada seluruh halaman lanjutan 75 item;
- baris panjang tidak terbelah antarhalaman;
- tidak ada clipping, overlap, black square, glyph rusak, atau halaman kosong;
- karakter `<priority>` dan `<escaped safely>` tampil sebagai teks, bukan markup;
- `é`, `ñ`, `ü`, ampersand, apostrophe, quote, dan simbol tampil benar;
- nilai besar tetap presisi, nilai negatif dan nol terformat benar;
- nilai null/kosong konsisten tampil sebagai `-`;
- terms dan completion block tidak terpotong.

## Reproduksi

```powershell
php scripts/render_off0401_fixture.php tmp/pdfs/off0405/short.html --case=short --official
php scripts/render_off0401_fixture.php tmp/pdfs/off0405/long-text.html --case=long-text --official
php scripts/render_off0401_fixture.php tmp/pdfs/off0405/special.html --case=special --official
php scripts/render_off0401_fixture.php tmp/pdfs/off0405/empty.html --case=empty --official
php scripts/render_off0401_fixture.php tmp/pdfs/off0405/many-items.html --case=many-items --official
```

HTML kemudian dirender dengan Chrome memakai flag yang sama seperti `QuotationPdfRenderer` dan PDF dirender ke PNG menggunakan `pdftoppm`.

## Verifikasi otomatis

```powershell
php artisan test tests/Unit/Quotations/QuotationValueFormatterTest.php tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationPdfGenerationTest.php
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **24 passed, 107 assertions, 1 skipped**; skip adalah gate Chrome OFF-0403 yang telah lulus opt-in;
- full default suite: **97 passed, 565 assertions, 6 skipped**;
- enam skip terdiri dari satu gate Chrome yang telah lulus dan lima gate PostgreSQL yang telah lulus melalui runner `OFF-0306`;
- Pint dan pemeriksaan whitespace lulus.
