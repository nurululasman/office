# Document Template Step 08 — Renderer Berbasis Snapshot

## Scope

Step ini membuat `QuotationTemplateRenderer` sebagai renderer aman untuk HTML
template quotation yang tersimpan pada snapshot.

Step ini belum:

- mengubah form pembuatan quotation;
- mengalihkan endpoint preview existing;
- mengalihkan job PDF existing;
- membuat shell CSS/A4 baru;
- melakukan migrasi template production.

## Sumber Data

Renderer membaca:

- `quotations.template_snapshot`;
- `quotations.template_content_sha256`;
- `quotations.placeholder_contract_version`;
- snapshot item schema;
- snapshot company profile;
- nilai quotation, item, dan terms.

Renderer tidak membaca ulang `document_templates.content_html` atau company
profile aktif. Perubahan template setelah draft dibuat tidak mengubah hasil
render quotation lama.

## Fail-closed Gate

Render ditolak bila:

- snapshot tidak tersedia atau tidak valid;
- checksum HTML tidak cocok;
- HTML snapshot tidak lagi sama dengan bentuk canonical hasil sanitizer;
- placeholder activation minimum tidak terpenuhi;
- masih terdapat placeholder yang tidak dapat diproses.

HTML dari snapshot tidak dievaluasi sebagai Blade, PHP, atau template expression.

## Placeholder Scalar

Placeholder scalar diubah menjadi nilai quotation/snapshot dan selalu di-escape.
Line break pada nilai multiline menjadi `<br>` setelah escaping.

Tanggal memakai formatter Indonesia existing. Nomor draft ditampilkan sebagai:

```text
DRAFT — nomor belum terbit
```

## Placeholder Struktural

Fragmen struktural hanya berasal dari partial Blade milik aplikasi:

- `company_logo`;
- `quotation_items`;
- `quotation_terms`;
- `signature_block`;
- `draft_watermark`.

Tabel memakai `QuotationTableLayout` dan `QuotationValueFormatter` existing,
termasuk grouped header, alignment, typed values, dan currency formatting.

Data item, terms, sender, attention, dan alt text logo tetap melalui escaping
Blade.

## Perluasan Presentasi Item

Step 8 diperluas untuk mendukung:

- `table` sebagai mode default dan kompatibel dengan seluruh template existing;
- `list` untuk daftar datar;
- `nested_list` untuk list dan sub-list.

Contoh schema list:

```json
{
  "columns": [
    {
      "key": "description",
      "label": "Description",
      "value_type": "text"
    }
  ],
  "presentation": {
    "type": "nested_list",
    "style": "ordered",
    "content_key": "description",
    "max_depth": 3
  }
}
```

`style` bernilai `ordered` atau `unordered`. Mode `list` selalu mempunyai
kedalaman satu. Mode `nested_list` menerima `max_depth` 2–5.

`content_key` wajib merujuk salah satu kolom schema. Nilainya tetap diproses
melalui `QuotationValueFormatter` dan escaping Blade.

## Hierarchy Item

`quotation_items.parent_item_id` menyimpan hierarchy parent-child. Posisi global
existing tetap dipertahankan agar urutan deterministik dan migration kompatibel.

Relasi model:

- `QuotationItem::parent`;
- `QuotationItem::children`.

Menghapus parent menghapus seluruh descendant melalui foreign-key cascade.

Renderer menolak:

- child pada mode flat `list`;
- parent yang bukan item quotation yang sama;
- hierarchy tanpa root;
- circular reference;
- depth yang melebihi konfigurasi snapshot.

Form quotation belum menyediakan kontrol tambah sub-item pada Step 8. Input
hierarchy menjadi scope Step 9.

## Logo

Renderer menerima `logoSource` dari caller yang dipercaya:

- preview browser nantinya dapat memberikan URL asset internal;
- PDF nantinya dapat memberikan data URI private/local.

Jika `logoSource` tidak diberikan, placeholder logo menghasilkan fragmen kosong.
Renderer tidak mengambil remote image.

## Draft dan Official

Parameter `isDraft` menentukan komponen draft:

- draft watermark ditampilkan hanya ketika `true`;
- output official tidak menyertakan draft watermark.

Nomor official berasal dari relasi document quotation. Integrasi workflow
preview/PDF tetap menjadi Step 10.

## Verifikasi

```powershell
php artisan test tests/Feature/QuotationTemplateRendererTest.php tests/Feature/DocumentTemplateManagementTest.php
php artisan view:cache
php artisan view:clear
php vendor/bin/pint --test app/Services/DocumentTemplates/QuotationTemplateRenderer.php tests/Feature/QuotationTemplateRendererTest.php
php artisan test tests/Feature/QuotationDraftManagementTest.php tests/Feature/QuotationWorkflowTest.php tests/Feature/QuotationPdfGenerationTest.php
php artisan test --compact
```

Hasil 23 Juli 2026:

```text
Renderer/template management:
13 passed, 105 assertions

Blade compilation dan Pint:
passed

Quotation draft/workflow/PDF regression:
26 passed, 245 assertions, 1 skipped, 1 existing CSS assertion failed

Full default suite:
145 passed, 834 assertions, 6 skipped, 3 existing failures
```

Kegagalan existing tetap berasal dari padding quotation
`0 2mm 1.8mm` versus Blade existing `0 2mm 0`. Renderer Step 8 belum digunakan
oleh Blade quotation existing. Dua kegagalan full-suite lain tetap berasal dari
expectation root `200` versus redirect existing `302`.

Real Chrome renderer, PostgreSQL gate, UAT, dan deployment belum dilakukan.

## Verifikasi Perluasan Item

```powershell
php artisan test tests/Feature/QuotationTemplateRendererTest.php tests/Feature/QuotationSchemaTest.php tests/Feature/DocumentTemplateManagementTest.php
```

Hasil:

```text
24 passed, 149 assertions
```

Dataset mencakup table existing, ordered list, nested list tiga tingkat,
escaping content, depth overflow, cycle, relasi parent/children, dan cascade
delete.
