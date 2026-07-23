# Document Template Step 05 — Integrasi TinyMCE Lokal

## Scope

Step ini mengganti pengalaman edit `content_html` dari textarea source biasa
menjadi WYSIWYG TinyMCE lokal, sambil mempertahankan textarea sebagai fallback.

Step ini belum menambahkan placeholder picker, sanitizer server, activation
content validator, upload gambar, atau renderer quotation.

## Asset

Editor menggunakan bundle existing:

```text
public/libs/tinymce/tinymce.min.js
```

Versi bundle: **TinyMCE 6.4.2**.

Tidak ada CDN, Tiny Cloud, API key, dependency npm baru, atau network request
yang diperlukan untuk memuat editor.

Konfigurasi aplikasi disimpan di:

```text
public/js/quotation-template-editor.js
```

## Toolbar dan Plugin

Plugin yang diaktifkan:

- `advlist`;
- `autolink`;
- `autoresize`;
- `code`;
- `lists`;
- `pagebreak`;
- `preview`;
- `searchreplace`;
- `table`;
- `visualblocks`;
- `wordcount`.

Fitur editor:

- heading dan paragraph;
- bold, italic, underline;
- alignment;
- ordered dan unordered list;
- tabel;
- page break;
- search/replace;
- visual blocks;
- code/source view;
- preview internal editor;
- word count;
- undo dan redo.

Plugin `image`, `media`, `link`, `template`, `emoticons`, dan `codesample` tidak
diaktifkan. Editor tidak menyediakan upload atau remote asset.

## Pembatasan Client-side

Konfigurasi client membatasi elemen ke konten dokumen umum seperti paragraph,
heading, list, tabel, `div`, `span`, dan horizontal rule.

Style yang diterima editor dibatasi pada:

- text alignment;
- margin kiri terbatas;
- table width dan border collapse;
- alignment dan vertical alignment cell.

Paste memakai plain text. URL conversion dan context menu dinonaktifkan.

Pembatasan TinyMCE hanya meningkatkan UX dan bukan security boundary. Request
tetap dianggap tidak dipercaya. Sanitasi server menjadi kewajiban Step 6.

## Sinkronisasi dan Fallback

- Perubahan, input, undo, dan redo disinkronkan kembali ke textarea.
- `tinymce.triggerSave()` dipanggil sebelum form submit.
- Jika library atau initialization gagal, warning ditampilkan dan textarea HTML
  tetap dapat digunakan.
- Form server-side, old input, maxlength, CSRF, optimistic lock, dan validasi
  Step 4 tetap berlaku.

## Verifikasi

```powershell
php artisan test tests/Feature/DocumentTemplateManagementTest.php tests/Feature/DocumentTemplateAuthorizationTest.php
php artisan view:cache
php artisan view:clear
php vendor/bin/pint --test tests/Feature/DocumentTemplateManagementTest.php
node --check public/js/quotation-template-editor.js
```

Hasil 23 Juli 2026:

```text
TinyMCE/CRUD/authorization:
12 passed, 97 assertions

Blade compilation:
passed

Pint:
passed

JavaScript syntax:
passed

Configured local plugin assets:
11 of 11 found
```

Real-browser authenticated visual QA, sanitizer security test, UAT, dan
deployment belum dilakukan.
