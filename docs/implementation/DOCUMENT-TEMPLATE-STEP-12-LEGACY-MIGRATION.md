# Step 12 — Migrasi Template Quotation Existing

Tanggal implementasi dan apply lokal: 23 Juli 2026.

## Strategi

Migrasi tidak menimpa template aktif existing dan tidak mengubah snapshot
quotation lama.

```text
legacy active version
  -> create next WYSIWYG version
  -> copy item schema/defaults
  -> validate sanitizer/placeholders/presentation
  -> archive legacy version
  -> activate WYSIWYG version
```

Versi baru ditandai dalam `editor_config` dengan marker
`document-template-step-12-v1` dan referensi template sumber.

HTML awal mengikuti layout quotation yang telah diverifikasi:

- company identity dan logo;
- nomor, tanggal, customer, sender, alamat, dan subject;
- intro, item table/list/sub-list, terms, closing;
- signature dan draft watermark.

## Command

Dry-run adalah mode default dan tidak mengubah data:

```powershell
php artisan office:quotation-templates:migrate-legacy
```

Apply membutuhkan actor aktif dengan permission template:

```powershell
php artisan db:seed --class=RolePermissionSeeder --force
php artisan office:quotation-templates:migrate-legacy --apply --actor=<USER_ID_OR_EMAIL>
php artisan office:quotation-templates:migrate-legacy
```

Apply bersifat idempotent. Dry-run sesudah apply harus melaporkan:

```text
0 candidate, 1 already migrated
```

## Preflight dan Backup

Sebelum apply:

```powershell
php artisan migrate:status
php scripts/backup_document_template_step12.php
php artisan migrate --force
php artisan office:quotation-templates:migrate-legacy
```

Script backup membuat PostgreSQL custom-format dump di:

```text
tmp/backups/document-template-step12-preflight.dump
```

Backup lokal saat apply:

```text
bytes: 85345
sha256: 6b7ea02a267cbcaa052ecbf2cd5c5e12040adf590cef4b0b4ada8b1c30dba596
```

Jangan menampilkan password database atau menyalin `.env` ke log.

## Bukti Apply PostgreSQL Lokal

Database efektif:

```text
driver: PostgreSQL
database: office
host: local
```

Preflight data:

```text
2 document templates
5 quotations
12 quotation items
```

Dry-run sebelum apply:

```text
1 candidate
template version: 2
columns: 4
quotations: 5
generated files: 4
```

Apply:

```text
1 template version dibuat dan diaktifkan
0 candidate, 1 already migrated
```

Kelima quotation lama tetap memiliki snapshot dan seluruhnya berhasil dirender.
Satu quotation lama aktual juga dicetak dengan Chrome menjadi PDF A4 satu
halaman:

`output/pdf/DOCUMENT-TEMPLATE-STEP-12-legacy-quotation-proof.pdf`

## PostgreSQL Finding

Apply pertama setelah permission sync menemukan bahwa PostgreSQL menolak
`MAX(version) FOR UPDATE`. Version allocator diperbaiki dengan mengunci seluruh
baris family lalu menghitung maksimum pada collection yang sudah terkunci.
Focused test sesudah perbaikan:

```text
9 passed, 61 assertions
```

Full default suite sesudah Step 12:

```text
153 passed, 888 assertions, 6 skipped, 2 existing failures
```

Blade compilation, Pint, dan PHP syntax lulus. Dua kegagalan existing tetap
berasal dari expectation root `/` berstatus `200` versus redirect aplikasi
`302`.

## Rollback

Rollback aplikasi yang direkomendasikan:

```powershell
php artisan office:quotation-templates:migrate-legacy --rollback --actor=<USER_ID_OR_EMAIL>
php artisan office:quotation-templates:migrate-legacy
```

Rollback:

- mengarsipkan versi WYSIWYG;
- mengaktifkan kembali versi legacy sumber;
- tidak menghapus versi mana pun;
- tidak mengubah snapshot quotation;
- menulis audit `quotation_template.migration_rolled_back`.

Rollback ini terbukti melalui automated test tetapi tidak dijalankan pada
database lokal setelah apply agar versi WYSIWYG tetap aktif.

Jangan memakai `php artisan migrate:rollback` untuk rollback operasional fitur
ini karena migration schema Step 2 akan menghapus kolom snapshot. Jika database
harus dipulihkan penuh, gunakan backup custom-format setelah menghentikan
aplikasi/worker dan mengikuti prosedur restore PostgreSQL terkontrol.

## Batas Bukti

Apply dan Chrome proof dilakukan pada PostgreSQL lokal. UAT, production,
deployment, dan restore drill backup belum dilakukan.
