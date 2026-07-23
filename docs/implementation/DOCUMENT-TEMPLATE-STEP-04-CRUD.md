# Document Template Step 04 — CRUD Template Quotation

## Scope

Step ini menyediakan alur administrasi server-rendered untuk:

- daftar, filter, dan pencarian template quotation;
- membuat draft keluarga template baru;
- melihat metadata dan source content;
- mengubah draft dengan optimistic lock;
- menduplikasi versi;
- mengaktifkan draft;
- mengarsipkan draft atau template aktif;
- navigasi berdasarkan permission.

Step ini belum mengaktifkan TinyMCE, sanitizer, placeholder picker, validator
konten activation, atau renderer template.

## Routes

```text
GET  /quotation-templates
GET  /quotation-templates/create
POST /quotation-templates
GET  /quotation-templates/{document_template}
GET  /quotation-templates/{document_template}/edit
PUT  /quotation-templates/{document_template}
POST /quotation-templates/{document_template}/duplicate
POST /quotation-templates/{document_template}/activate
POST /quotation-templates/{document_template}/archive
```

Seluruh mutation route memakai `throttle:office-mutation` serta middleware
`auth` dan `sso.session` dari route group existing.

Tidak ada endpoint delete permanen.

## Form dan Validasi

Form draft menangani:

- company profile aktif;
- `template_key` kebab-case yang unik per keluarga;
- nama;
- HTML source;
- item schema JSON dengan array `columns`;
- default intro dan closing;
- default terms, satu baris per term;
- lock version ketika update.

`template_key` tidak dapat diubah setelah keluarga dibuat. Versi tidak diterima
dari update payload dan ditentukan server.

Pada Step 4, konten HTML memakai textarea biasa. Source pada halaman detail
ditampilkan menggunakan escaping Blade dan tidak dieksekusi sebagai HTML.

## Authorization

- `document-admin` dan `system-admin` memperoleh UI sesuai policy.
- `auditor` dapat membuka index dan detail, tetapi tidak melihat aksi mutation.
- role tanpa `quotation-template.view` menerima `403`.
- controller dan lifecycle service sama-sama menjalankan authorization.

## Lifecycle

- Create menghasilkan version `1` berstatus `draft`.
- Update hanya menerima field konten/default yang boleh diedit.
- Duplicate menghasilkan nomor version berikutnya sebagai `draft`.
- Activate mengarsipkan versi aktif lama dalam keluarga yang sama.
- Archive tidak dapat menghapus template aktif terakhir dalam katalog
  quotation.
- Stale lock version ditolak tanpa perubahan parsial.

## Security Boundary

HTML belum disanitasi pada Step 4 dan karenanya:

- tidak pernah dirender menggunakan raw Blade;
- tidak digunakan oleh preview/PDF existing;
- hanya ditampilkan sebagai source escaped;
- activation pada tahap ini hanya mengubah lifecycle persistence.

Sebelum renderer menggunakan `content_html`, Step 6 wajib menambahkan sanitasi
server dan activation validator. Status active pada Step 4 belum menjadi bukti
bahwa konten aman untuk dirender.

## Verifikasi

```powershell
php artisan test tests/Feature/DocumentTemplateManagementTest.php tests/Feature/DocumentTemplateAuthorizationTest.php
php artisan view:cache
php artisan view:clear
php vendor/bin/pint --test
php artisan route:list --name=quotation-templates
```

Hasil 23 Juli 2026:

```text
CRUD, authorization, lifecycle:
11 passed, 85 assertions

Blade compilation:
passed

Pint:
passed

Quotation/authorization/cutover regression:
25 passed, 131 assertions, 1 existing CSS assertion failed

Full default suite:
133 passed, 760 assertions, 6 skipped, 3 existing failures
```

Kegagalan regression adalah assertion padding quotation existing
`0 2mm 1.8mm` versus Blade existing `0 2mm 0`; Step 4 tidak mengubah CSS
quotation. Dua kegagalan full-suite lainnya tetap berasal dari expectation root
`200` versus redirect existing `302`.

Gate PostgreSQL, browser visual QA, UAT, dan deployment belum dilakukan.
