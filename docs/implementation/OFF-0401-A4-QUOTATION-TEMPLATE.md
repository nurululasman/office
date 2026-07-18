# OFF-0401 - A4 quotation template

## Sumber visual

- Referensi: `D:\MAMAN\YAHER\QUOTATION DEPO PEINITIPAN JBLU(1).pdf`.
- Referensi merupakan scan satu halaman tanpa text layer, berukuran `589.68 x 835.92 pt` (mendekati A4).
- Asset resmi: `public/static/jblu.png`.
- Asset berukuran `896 x 755 px`, rasio `1.187`, ukuran `734362` byte, dan SHA-256 `CF7F4C45F4D23C345E35D17A02758D92CD644E2FFE222F23EFE60F025A14DBCC`.

Asset logo ditampilkan dalam kotak `38 x 32 mm` menggunakan `object-fit: contain` dan `object-position: center`. Pengaturan ini mempertahankan rasio sumber, tidak melakukan crop, dan tidak meregangkan gambar.

## Implementasi

Template `resources/views/quotations/document.blade.php` menjadi satu sumber HTML/CSS untuk preview dan renderer PDF berikutnya. Struktur mengikuti lampiran:

- letterhead identitas perusahaan di kiri dan logo di kanan;
- garis biru horizontal;
- metadata quotation/to/subject serta date/from dalam dua kolom;
- salam dan pengantar;
- tabel quotation;
- terms, penutup, serta blok `Sincerely Yours` dan `Approved By`;
- watermark/footer draft yang tidak menjadi bagian dokumen final.

Kontrak print memakai `@page { size: A4 portrait; margin: 0; }`, halaman `210 x 297 mm`, margin konten eksplisit, font Arial/Helvetica, dan background tabel yang tetap terbaca saat dicetak hitam-putih.

`scripts/render_off0401_fixture.php` menghasilkan fixture HTML mandiri dengan logo data URI agar hasil QA tidak tergantung web server atau resource eksternal.

## Visual QA

Fixture dirender dengan Chrome headless ke `output/pdf/OFF-0401-template-proof.pdf`, lalu PDF dirender ulang ke PNG 150 DPI menggunakan Poppler dan diperiksa secara visual.

Hasil:

- output tepat satu halaman A4 `594.96 x 841.92 pt`;
- logo tajam pada ukuran cetak, rasio terjaga, tanpa crop/stretch;
- letterhead, garis brand, metadata, tabel, terms, penutup, dan signature blocks sejajar;
- tidak ada clipping, overlap, glyph rusak, atau konten keluar margin;
- watermark berada di belakang isi dan teks tetap terbaca;
- SHA-256 PDF bukti: `BEF195C3A9904FAF12BADF5838BF1D0763B4E5A2D8D7B727ADEB7FAF5D936897`.

## Verifikasi otomatis

```powershell
php scripts/render_off0401_fixture.php
php artisan test tests/Feature/QuotationDraftManagementTest.php
php artisan test
```

Hasil 18 Juli 2026:

- targeted: **9 passed, 59 assertions**;
- full default suite: **88 passed, 510 assertions, 5 skipped**;
- lima gate PostgreSQL yang skipped pada mode default telah lulus melalui runner integration di `OFF-0306`;
- Blade compilation dan Pint lulus.
