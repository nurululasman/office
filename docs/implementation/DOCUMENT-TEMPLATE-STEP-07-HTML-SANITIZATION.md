# Document Template Step 07 — Sanitasi dan Normalisasi HTML

## Scope

Step 7 memastikan HTML TinyMCE tidak pernah disimpan sebagai request mentah.
Sanitasi dijalankan pada service boundary sebelum validasi placeholder sehingga
aturan yang disahkan selalu berlaku terhadap HTML canonical yang benar-benar
disimpan.

Renderer template, integrasi quotation, dan PDF berada di luar scope milestone
ini.

## Alur penyimpanan

Pada create dan update draft, `DocumentTemplateLifecycle` menjalankan:

1. `DocumentTemplateHtmlSanitizer::sanitize`;
2. validasi placeholder terhadap HTML hasil sanitasi;
3. penyimpanan HTML tersanitasi;
4. penghitungan ulang `content_sha256` oleh model;
5. audit metadata dan checksum tanpa menyalin body HTML.

Aktivasi melakukan sanitasi ulang sebagai pertahanan terhadap record yang
dibuat di luar lifecycle service.

## Allowlist

Elemen yang didukung terbatas pada struktur dokumen:

- paragraph, heading, line break, emphasis, blockquote;
- ordered/unordered list;
- table, section, row, dan cell;
- `div`, `span`, dan horizontal rule.

Atribut hanya dipertahankan per tag. Nilai numerik untuk `start`, `border`,
`colspan`, dan `rowspan` dibatasi; `scope` hanya menerima nilai tabel yang
dikenal. Class selain `page-break` dibuang.

Style yang diterima hanya:

- alignment teks;
- vertical alignment pada cell;
- margin kiri terbatas pada text block;
- persentase width dan border collapse pada table.

Positioning, warna, background, URL CSS, font eksternal, dan properti lainnya
dibuang.

## Elemen aktif dan URL

`script`, `style`, `iframe`, form, embedded object, media, SVG, MathML, template,
meta, base, dan stylesheet dibuang beserta isinya. Event handler dan atribut
yang tidak masuk allowlist juga dibuang.

Anchor tidak diizinkan sebagai elemen aktif: tag serta URL-nya dibuang, tetapi
label teks dipertahankan.

## Kebijakan gambar

HTML template tidak menerima tag `img`, data URI, URL gambar eksternal, maupun
upload dari TinyMCE. Logo hanya berasal dari Company Profile dan dimasukkan oleh
renderer melalui placeholder `{{ company_logo }}`.

Media manager private belum diaktifkan. Perluasan ini membutuhkan keputusan
terpisah mengenai authorization, checksum, retention, dan snapshot.

## Batas ukuran

Request membatasi `content_html` sampai 200.000 karakter. Sanitizer juga
menerapkan batas fail-closed `200.000 byte` sebelum dan sesudah normalisasi.
Batas service mencegah pemanggilan CLI, job, atau service internal melewati
proteksi HTTP.

Konten kosong sebelum maupun sesudah sanitasi ditolak.

## Verifikasi lokal

Perintah:

```powershell
vendor\bin\pint app\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer.php tests\Feature\DocumentTemplateManagementTest.php
php artisan test --compact tests\Feature\DocumentTemplateManagementTest.php tests\Feature\DocumentTemplateAuthorizationTest.php
```

Hasil 23 Juli 2026:

```text
Pint: passed
17 tests passed
140 assertions
```

Pengujian mencakup:

- create/update menyimpan HTML tersanitasi;
- sanitasi ulang pada aktivasi;
- script, iframe, image, event handler, URL eksternal, comment, dan CSS
  berbahaya dibuang;
- label anchor, Unicode, style dan atribut allowlist dipertahankan;
- placeholder divalidasi setelah sanitasi;
- HTML lebih dari batas ditolak langsung oleh sanitizer.

Evidence ini bersifat lokal. UAT dan deployment production tidak dilakukan
dalam Step 7.
