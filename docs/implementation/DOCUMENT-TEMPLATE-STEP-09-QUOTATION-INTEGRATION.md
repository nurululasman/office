# Step 9 — Integrasi Template ke Pembuatan Quotation

Tanggal implementasi: 23 Juli 2026.

## Ruang Lingkup

Step ini menghubungkan template quotation aktif ke form draft. Refactor shell
preview dan PDF agar memakai renderer baru tetap menjadi Step 10.

## Pilihan Template dan Default

- Form hanya menampilkan template quotation berstatus `active`.
- Label pilihan menampilkan nama, versi, dan company profile.
- Template terpilih menyediakan `item_schema`, `default_intro_text`,
  `default_closing_text`, dan `default_terms`.
- Saat pilihan template berubah, field item dibentuk ulang berdasarkan schema
  dan nilai default dimuat di browser.
- Draft existing mempertahankan isi dan schema snapshot selama template tidak
  diganti.

## Item Table, List, dan Sub-list

Semua tipe presentasi memakai field berdasarkan `item_schema.columns`.

- `table` dan `list` menyimpan item datar.
- `nested_list` menampilkan pilihan `Sub-item dari` untuk item kedua dan
  seterusnya.
- Browser hanya menawarkan item sebelumnya sebagai parent.
- Request tetap memvalidasi parent di server, menolak parent pada tipe non
  nested, forward reference, dan kedalaman di atas `max_depth`.
- Persistence mengubah `parent_index` form menjadi UUID `parent_item_id`.
- Penghapusan baris di browser menyesuaikan indeks parent; child dari baris yang
  dihapus menjadi item utama.

Posisi item tetap unik secara global dalam satu quotation agar urutan snapshot
deterministik.

## Snapshot Template

Saat draft dibuat atau template draft diganti, quotation menyimpan:

- template ID, key, dan versi;
- HTML template dan checksum;
- item schema;
- placeholder contract version;
- snapshot company profile dan branding.

Mengubah template sumber sesudah draft disimpan tidak mengubah snapshot draft.

## Preview Sebelum Workflow

Tombol `Simpan & preview` menyimpan draft terlebih dahulu lalu mengarahkan user
ke preview draft existing. Preview tersebut masih memakai Blade quotation lama;
penyatuan preview browser dan PDF dengan `QuotationTemplateRenderer` dilakukan
pada Step 10.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationTemplateRendererTest.php tests/Feature/QuotationSchemaTest.php
php artisan view:cache
php artisan view:clear
php vendor/bin/pint --test app/Http/Controllers/QuotationController.php app/Http/Requests/QuotationDraftRequest.php tests/Feature/QuotationDraftManagementTest.php
php artisan test --compact
```

Hasil 23 Juli 2026:

```text
Kasus baru Step 9:
3 passed, 18 assertions

Quotation draft/renderer/schema:
27 passed, 144 assertions, 1 existing CSS assertion failed

Blade compilation dan Pint:
passed

Full default suite:
148 passed, 852 assertions, 6 skipped, 3 existing failures
```

Kegagalan existing tetap terdiri dari assertion padding quotation
`0 2mm 1.8mm` versus Blade existing `0 2mm 0`, serta dua expectation root
`200` versus redirect existing `302`. Tidak ada kegagalan baru dari Step 9.

PostgreSQL, real Chrome PDF, UAT, dan deployment bukan bukti dalam Step 9 ini.
