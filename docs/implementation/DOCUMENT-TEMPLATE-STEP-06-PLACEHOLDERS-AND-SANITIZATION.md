# Document Template Step 06–07 — Placeholder dan Sanitasi HTML

## Scope

Implementasi ini menyelesaikan dua bagian yang saling bergantung:

- Step 6: placeholder picker, allowlist, placement, dan activation validator;
- Step 7: sanitasi serta normalisasi HTML pada server.

Sanitasi diselesaikan bersama Step 6 karena template tidak boleh dinyatakan
valid untuk activation sebelum HTML yang diperiksa adalah HTML tersanitasi yang
benar-benar disimpan.

Renderer quotation belum menggunakan `content_html` pada tahap ini.

## Placeholder Picker

TinyMCE mempunyai menu **Placeholder** dengan dua kelompok:

- **Data** untuk placeholder scalar;
- **Komponen** untuk placeholder struktural.

Placeholder scalar disisipkan pada posisi cursor sebagai token teks. Placeholder
struktural disisipkan sebagai satu-satunya isi elemen `div`.

Picker mencakup seluruh allowlist dari kontrak Step 1, termasuk:

- nomor, tanggal, subject, customer, attention, sender, currency;
- intro dan closing;
- identitas perusahaan;
- company logo;
- quotation items;
- quotation terms;
- signature block;
- draft watermark.

Page break TinyMCE dinormalisasi menjadi:

```html
<hr class="page-break">
```

## Validasi Placeholder Server

Server menolak:

- placeholder yang tidak berada dalam allowlist;
- sintaks placeholder malformed;
- placeholder struktural yang muncul lebih dari satu kali;
- placeholder struktural yang bercampur dengan teks;
- placeholder struktural di luar elemen `div` mandiri.

Draft boleh belum memiliki semua placeholder wajib. Activation mewajibkan:

- `quotation_number`;
- `quotation_date`;
- `subject`;
- `customer_name`;
- `sender_name`;
- tepat satu `quotation_items`;
- minimal salah satu dari `company_display_name` atau `company_legal_name`.

Activation yang gagal tidak mengarsipkan versi aktif sebelumnya dan tidak
menghasilkan audit activation palsu.

## Sanitasi Server

`DocumentTemplateHtmlSanitizer` dijalankan oleh lifecycle service pada create
dan update. Karena berada di service boundary, pemanggilan dari luar controller
tetap disanitasi.

Elemen yang diizinkan dibatasi pada:

- paragraph, line break, emphasis, dan heading;
- blockquote dan list;
- tabel beserta section, row, dan cell;
- `div`, `span`, dan horizontal rule.

Elemen aktif atau berisiko dibuang beserta isinya, termasuk:

- `script`, `style`, `iframe`, `object`, `embed`;
- form dan seluruh input;
- video, audio, SVG, MathML, dan template;
- link stylesheet, meta, dan base.

Elemen lain seperti anchor di-unwrap sehingga teksnya dipertahankan tetapi link
dan URL-nya tidak disimpan.

Semua event handler dan atribut selain allowlist dibuang.

## Style Allowlist

Style yang diizinkan:

- `text-align`;
- `vertical-align` pada cell;
- `margin-left` terbatas pada text blocks;
- `border-collapse` dan persentase width pada table.

Warna, background, positioning, z-index, URL, font remote, page CSS, dan style
lain dibuang.

Class yang dipertahankan hanya `page-break`.

## Kebijakan Gambar

Gambar tidak dapat dimasukkan melalui HTML editor. Tag image dan plugin image
tidak berada dalam allowlist.

Untuk versi ini, logo hanya berasal dari snapshot `company_profile` melalui
placeholder `company_logo`. Media manager private atau upload gambar template
ditunda sampai ada kebutuhan bisnis dan kontrak storage/checksum/retention yang
disetujui.

## Normalisasi dan Checksum

- HTML diparse sebagai UTF-8 menggunakan DOM.
- Unicode dipertahankan.
- Attribute dan style dinormalisasi.
- Comment dibuang; page break memakai elemen canonical.
- `content_sha256` dihitung ulang oleh model dari HTML tersanitasi.
- Audit hanya menyimpan checksum dan metadata, bukan body HTML penuh.

## Verifikasi

```powershell
php artisan test tests/Feature/DocumentTemplateManagementTest.php tests/Feature/DocumentTemplateAuthorizationTest.php
php artisan test tests/Feature/AuthorizationFoundationTest.php tests/Feature/InitialDataCutoverTest.php tests/Feature/QuotationSchemaTest.php tests/Feature/QuotationWorkflowTest.php
php vendor/bin/pint --test
node --check public/js/quotation-template-editor.js
php artisan view:cache
php artisan view:clear
php artisan test
```

Hasil 23 Juli 2026:

```text
Placeholder/sanitizer/CRUD/authorization:
15 passed, 121 assertions

Authorization/cutover/schema/workflow regression:
27 passed, 213 assertions

Pint, JavaScript syntax, dan Blade compilation:
passed

Full default suite:
137 passed, 796 assertions, 6 skipped, 3 existing failures
```

Tiga kegagalan existing tetap sama:

- dua expectation root `200` versus redirect existing `302`;
- satu assertion padding quotation `0 2mm 1.8mm` versus Blade existing
  `0 2mm 0`.

Real-browser visual QA, PostgreSQL gate, UAT, deployment, dan renderer template
belum dilakukan.
