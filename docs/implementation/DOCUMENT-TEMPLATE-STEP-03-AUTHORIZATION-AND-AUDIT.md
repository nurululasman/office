# Document Template Step 03 — Authorization dan Audit

## Scope

Step ini menambahkan:

- permission khusus template quotation;
- role mapping;
- `DocumentTemplatePolicy`;
- registrasi policy;
- lifecycle service transaksional;
- optimistic lock;
- audit create, update, versioning, activation, dan archive.

Step ini belum menambahkan route, controller, form request, halaman CRUD,
TinyMCE, sanitizer, placeholder parser, atau renderer.

## Permission

Permission baru:

- `quotation-template.view`;
- `quotation-template.create`;
- `quotation-template.update`;
- `quotation-template.activate`;
- `quotation-template.archive`.

Role mapping:

| Role | Hak template quotation |
|---|---|
| `system-admin` | Seluruh permission |
| `document-admin` | View, create, update, activate, archive |
| `auditor` | View |
| Role lain | Tidak memperoleh akses secara implisit |

Permission lama `templates.read` dan `templates.manage` dipertahankan untuk
kompatibilitas, tetapi policy template quotation menggunakan permission baru
yang lebih sempit.

## Policy

`DocumentTemplatePolicy` menerapkan aturan:

- hanya template bertipe `quotation` yang masuk scope;
- update dan activate hanya diizinkan untuk status `draft`;
- archive hanya untuk status `draft` atau `active`;
- archived tidak dapat diedit atau diaktifkan kembali;
- permission selalu dibaca dari database melalui mekanisme authorization
  existing.

Service lifecycle juga memanggil policy secara langsung agar penggunaan service
di luar controller tetap fail-closed.

## Lifecycle Service

`DocumentTemplateLifecycle` menyediakan:

- `createDraft`;
- `updateDraft`;
- `createVersion`;
- `activate`;
- `archive`.

Aturan penting:

- create selalu memaksa `type=quotation` dan `status=draft`;
- field lifecycle tidak dapat disisipkan melalui payload update;
- update memakai row lock dan `lock_version`;
- version baru menyalin konten sumber sebagai draft dengan nomor berikutnya;
- activation mengarsipkan versi aktif sebelumnya dalam transaksi yang sama;
- template aktif terakhir pada seluruh katalog quotation tidak dapat diarsipkan;
- stale mutation gagal tanpa perubahan parsial atau audit palsu.

Validasi isi template, sanitasi HTML, dan validasi placeholder sengaja belum
dilakukan oleh service ini karena merupakan scope Step 5–6. Sebelum endpoint
aktivasi diekspos pada CRUD, Step 4 harus memakai form request dan gate; Step 6
akan menambahkan activation validator.

## Audit

Action yang dicatat:

- `quotation_template.created`;
- `quotation_template.updated`;
- `quotation_template.version_created`;
- `quotation_template.activated`;
- `quotation_template.archived`.

Audit menyimpan:

- actor;
- template ID, key, type, version, dan name;
- status;
- content SHA-256;
- lock version;
- company profile;
- metadata activation;
- source version atau alasan archive pada context.

Audit tidak menyimpan seluruh `content_html`, sehingga tidak menggandakan body
template dan tidak menambah paparan konten secara tidak perlu.

## Verifikasi

```powershell
php artisan test tests/Feature/AuthorizationFoundationTest.php tests/Feature/DocumentTemplateAuthorizationTest.php tests/Feature/InitialDataCutoverTest.php
php artisan test tests/Feature/QuotationSchemaTest.php tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationWorkflowTest.php
php vendor/bin/pint --test
php artisan test
```

## Hasil Verifikasi 23 Juli 2026

Targeted authorization, lifecycle, audit, dan cutover:

```text
15 passed, 78 assertions
```

Quotation schema/workflow regression:

```text
27 passed, 235 assertions, 1 existing CSS assertion failed
```

Full default suite:

```text
128 passed, 712 assertions, 6 skipped, 3 existing failures
```

Tiga kegagalan existing sama seperti baseline Step 2:

- dua test root masih mengharapkan `200`, sedangkan aplikasi mengembalikan
  redirect `302`;
- satu test quotation mengharapkan padding `0 2mm 1.8mm`, sedangkan Blade
  existing menggunakan `0 2mm 0`.

Pint untuk seluruh file Step 3 lulus.

Gate PostgreSQL dan UAT tidak termasuk bukti Step 3.
