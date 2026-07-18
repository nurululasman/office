# OFF-0402 - Dynamic table and multi-page quotation

## Implementasi

`QuotationTableLayout` membentuk layout tabel dari snapshot definisi kolom quotation. Setiap kolom memakai `key`, `label`, dan `value_type` miliknya sendiri sehingga renderer tidak bergantung pada nama khusus seperti `rate_20` atau `rate_40`. Hint opsional yang didukung adalah `group`/`header_group`, `width`, dan `align`.

Template `resources/views/quotations/document.blade.php` sekarang:

- menghasilkan `colgroup` dan sel nilai berdasarkan key dinamis;
- menggabungkan kolom berurutan yang memiliki group sama menjadi header bertingkat dengan `colspan`, sementara kolom tanpa group memakai `rowspan`;
- tetap menghasilkan satu baris header saat tidak ada group;
- mengulang `thead` pada setiap halaman cetak;
- mencegah satu baris item terpotong di antara halaman;
- menjaga terms, penutup, dan dua blok tanda tangan tetap terbaca; penutup dan tanda tangan dibungkus sebagai satu completion block agar tidak terpisah.

Fixture QA memakai kolom `description`, `rate_20`, `rate_40`, dan `note`. Label `20'` dan `40'` membuktikan karakter apostrof aman dirender, sementara pengambilan nilai tetap melalui key yang didefinisikan schema.

## Visual QA

`scripts/render_off0401_fixture.php` mendukung fixture pendek dan `--long`. Keduanya dirender dengan Chrome headless, dianalisis dengan Poppler, dirender kembali ke PNG 150 DPI, lalu diperiksa secara visual.

Hasil:

- `output/pdf/OFF-0402-grouped-header-short.pdf`: 1 halaman A4 `594.96 x 841.92 pt`, SHA-256 `CE1F8FD631D0CE7912E3754E7BC213E74F1801D7E1C51597DC2484545276BC2C`;
- `output/pdf/OFF-0402-multipage-long.pdf`: 4 halaman A4 `594.96 x 841.92 pt`, SHA-256 `B556C2209034744CE9366956FEF6335358ABAA5770A89F5F37A13E9FDA61A6FB`;
- header `Full Container (Dry)` serta subheader `20'`/`40'` berulang pada halaman lanjutan;
- baris dengan deskripsi panjang membungkus teks tanpa terpotong antarhalaman;
- terms tetap mengikuti tabel dan completion block tampil utuh pada halaman terakhir;
- tidak ada halaman kosong, clipping, overlap, atau glyph rusak.

## Verifikasi otomatis

```powershell
php scripts/render_off0401_fixture.php output/pdf/OFF-0402-grouped-header-short.html
php scripts/render_off0401_fixture.php output/pdf/OFF-0402-multipage-long.html --long
php artisan view:cache
php artisan view:clear
php artisan test tests/Unit/Quotations/QuotationTableLayoutTest.php tests/Feature/QuotationDraftManagementTest.php
php artisan test
```

Hasil 18 Juli 2026:

- Pint dan kompilasi seluruh Blade template lulus;
- targeted: **11 passed, 74 assertions**;
- full default suite: **90 passed, 525 assertions, 5 skipped**;
- lima gate PostgreSQL yang skipped pada mode default telah lulus melalui runner integration di `OFF-0306`.
