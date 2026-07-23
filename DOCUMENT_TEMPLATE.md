# Rencana Implementasi Template Dokumen Quotation

## 1. Tujuan

Pengguna yang berwenang dapat membuat dan mengelola template quotation menggunakan TinyMCE lokal di `public/libs/tinymce`. Ketika membuat quotation baru, pengguna cukup memilih template aktif lalu mengisi data quotation.

Implementasi menggunakan pendekatan **WYSIWYG terkontrol**:

- pengguna dapat menyusun konten dokumen;
- data dinamis dimasukkan melalui placeholder yang disediakan aplikasi;
- tabel item, terms, branding, sanitasi HTML, dan rendering PDF tetap dikendalikan server;
- perubahan template tidak boleh mengubah quotation lama atau PDF final.

## 2. Prinsip Arsitektur

1. TinyMCE tidak menjalankan JavaScript atau template engine server.
2. HTML dari editor harus disanitasi menggunakan allowlist.
3. Placeholder diproses oleh renderer khusus, bukan dengan mengeksekusi Blade dari HTML pengguna.
4. Preview browser dan PDF menggunakan renderer dan snapshot data yang sama.
5. Template menggunakan versioning; template yang sudah digunakan tidak diedit secara destruktif.
6. Quotation menyimpan snapshot template dan schema agar perubahan template baru tidak memengaruhi dokumen lama.
7. Aktivitas pembuatan, perubahan, aktivasi, dan pengarsipan template harus diaudit.

## 3. Tahapan Implementasi

### Step 1 — Tetapkan spesifikasi placeholder dan lifecycle template ✅

Tentukan bagian yang dapat diedit melalui TinyMCE dan placeholder yang tersedia, misalnya:

- `{{ quotation_number }}`
- `{{ quotation_date }}`
- `{{ customer_name }}`
- `{{ customer_address }}`
- `{{ attention_name }}`
- `{{ attention_role }}`
- `{{ subject }}`
- `{{ sender_name }}`
- `{{ sender_title }}`
- `{{ quotation_items }}`
- `{{ quotation_terms }}`

Tetapkan juga:

- placeholder wajib dan opsional;
- batas jumlah kemunculan placeholder tertentu;
- perilaku `intro_text`, `closing_text`, dan default terms;
- status template: `draft`, `active`, dan `archived`;
- aturan aktivasi, duplikasi, versioning, dan pengarsipan;
- aturan template yang telah digunakan oleh quotation.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** spesifikasi placeholder, lifecycle, dan acceptance criteria tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-01-SPECIFICATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-01-SPECIFICATION.md).

### Step 2 — Perluas database dan model template ✅

Perluas struktur `document_templates` untuk memisahkan:

- HTML hasil TinyMCE;
- schema kolom item quotation;
- konfigurasi editor;
- status dan metadata versioning;
- pembuat, pengubah, dan waktu aktivasi.

Perubahan template aktif dibuat sebagai versi baru. Record versi lama tetap tersedia untuk quotation yang sudah menggunakannya.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** migration, model, snapshot quotation, pengujian schema, dan catatan
implementasi tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-02-PERSISTENCE.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-02-PERSISTENCE.md).

### Step 3 — Tambahkan permission, policy, dan audit ✅

Tambahkan permission:

- `quotation-template.view`
- `quotation-template.create`
- `quotation-template.update`
- `quotation-template.activate`
- `quotation-template.archive`

Hak awal:

- `system-admin` memperoleh seluruh akses;
- `document-admin` dapat mengelola dan mengaktifkan template;
- role quotation lainnya hanya dapat menggunakan template aktif sesuai kewenangannya.

Semua perubahan material dicatat dalam audit log.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** permission catalog, role mapping, policy fail-closed, audited
lifecycle service, dan pengujian tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-03-AUTHORIZATION-AND-AUDIT.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-03-AUTHORIZATION-AND-AUDIT.md).

### Step 4 — Buat CRUD template quotation ✅

Tambahkan halaman administrasi untuk:

- daftar dan pencarian template;
- membuat template draft;
- mengubah template draft;
- preview dengan sample data;
- menduplikasi template sebagai versi baru;
- mengaktifkan versi;
- mengarsipkan template.

Route yang disarankan:

```text
/settings/quotation-templates
```

Template yang sudah digunakan tidak boleh dihapus permanen.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** route, controller, form request, halaman administrasi, lifecycle
actions, navigasi, dan pengujian tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-04-CRUD.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-04-CRUD.md).

### Step 5 — Integrasikan TinyMCE lokal ✅

Gunakan:

```text
public/libs/tinymce/tinymce.min.js
```

Fitur awal yang diizinkan:

- heading dan paragraph;
- bold, italic, dan underline;
- alignment;
- ordered dan unordered list;
- tabel sederhana;
- page break;
- undo dan redo;
- paste dengan normalisasi;
- menu penyisipan placeholder.

Fitur yang tidak diizinkan:

- JavaScript;
- iframe;
- form;
- event handler HTML;
- asset eksternal sembarangan;
- CSS bebas yang dapat merusak layout PDF.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** TinyMCE lokal, konfigurasi editor terkontrol, sinkronisasi form,
fallback textarea, dan pengujian tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-05-TINYMCE.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-05-TINYMCE.md).

### Step 6 — Buat placeholder picker ✅

Tambahkan tombol TinyMCE **Insert Placeholder**. Pengguna memilih nama yang mudah dipahami, kemudian editor menyisipkan token yang sesuai.

Contoh:

```text
Customer Name    → {{ customer_name }}
Quotation Items  → {{ quotation_items }}
Terms            → {{ quotation_terms }}
```

Server memvalidasi:

- hanya placeholder allowlist yang dapat disimpan;
- placeholder wajib tersedia;
- placeholder tidak dikenal ditolak;
- placeholder struktural tidak muncul melebihi batas.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** picker scalar/struktural, allowlist, aturan placement, dan activation
validator tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-06-PLACEHOLDERS-AND-SANITIZATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-06-PLACEHOLDERS-AND-SANITIZATION.md).

### Step 7 — Sanitasi dan normalisasi HTML ✅

Pada penyimpanan template:

- sanitasi tag dan atribut berdasarkan allowlist;
- hapus script, event handler, iframe, dan style berbahaya;
- normalisasi URL serta sumber gambar;
- batasi ukuran HTML;
- validasi placeholder setelah sanitasi;
- simpan HTML hasil sanitasi, bukan request mentah.

Tentukan secara eksplisit apakah gambar hanya berasal dari company profile atau dapat diunggah melalui media manager private.

**Status:** selesai dan diverifikasi ulang sebagai milestone mandiri pada 23 Juli 2026.

Implementasi awal dikerjakan bersama Step 6 karena activation validator tidak
boleh mengesahkan HTML yang belum dinormalisasi server. Eksekusi mandiri Step 7
kemudian mengaudit ulang seluruh boundary dan menambahkan batas 200.000 byte
langsung pada service agar tidak dapat dilewati oleh pemanggilan non-HTTP.

**Hasil:** HTML tersanitasi dan ternormalisasi sebelum disimpan, sumber gambar
dibatasi ke Company Profile melalui placeholder, serta ukuran diperiksa pada
request dan service boundary. Bukti mandiri tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-07-HTML-SANITIZATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-07-HTML-SANITIZATION.md);
kontrak gabungan Step 6–7 tetap tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-06-PLACEHOLDERS-AND-SANITIZATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-06-PLACEHOLDERS-AND-SANITIZATION.md).

### Step 8 — Buat renderer template ✅

Buat service khusus, misalnya `QuotationTemplateRenderer`, dengan tanggung jawab:

1. membaca versi template yang dipilih;
2. mengganti placeholder scalar dengan nilai yang sudah di-escape;
3. merender `quotation_items` melalui partial Blade yang aman;
4. merender terms berdasarkan urutan;
5. menggabungkan branding company profile;
6. menghasilkan HTML yang sama untuk preview dan PDF.

HTML buatan pengguna tidak boleh dievaluasi sebagai Blade atau PHP.

**Status:** selesai pada 23 Juli 2026.

**Hasil:** renderer berbasis snapshot, checksum gate, scalar escaping, komponen
struktural Blade, pilihan item `table`/`list`/`nested_list`, hierarchy
parent-child, serta pengujian tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-08-RENDERER.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-08-RENDERER.md).

### Step 9 — Integrasikan template ke pembuatan quotation

Ketika membuat quotation:

- tampilkan pilihan template aktif beserta versinya;
- muat default intro, closing, terms, dan schema item bila tersedia;
- bentuk field item berdasarkan schema template;
- sediakan preview sebelum workflow diselesaikan.

Quotation menyimpan snapshot:

- ID dan versi template;
- HTML template;
- item schema;
- konfigurasi atau branding yang diperlukan untuk reproduksi dokumen.

**Hasil:** alur pilih template lalu isi quotation berjalan end-to-end.

**Status 23 Juli 2026:** selesai. Form quotation menampilkan template aktif
beserta versi, memuat default intro/closing/terms, membentuk field dari schema,
mendukung parent-child untuk `nested_list`, memperbarui snapshot saat template
draft diganti, dan menyediakan aksi simpan lalu preview. Bukti implementasi ada
di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-09-QUOTATION-INTEGRATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-09-QUOTATION-INTEGRATION.md).

### Step 10 — Sesuaikan preview dan PDF

Refactor `resources/views/quotations/document.blade.php` menjadi shell dokumen yang menangani:

- ukuran halaman dan margin;
- font;
- header dan footer;
- aturan page break;
- HTML hasil renderer;
- tabel item;
- terms;
- signature area.

Preview browser dan job PDF wajib menggunakan hasil renderer yang sama.

**Hasil:** preview dan PDF konsisten.

**Status 23 Juli 2026:** selesai. Preview browser dan PDF official menggunakan
`QuotationTemplateRenderer` melalui layanan dokumen yang sama, lalu dibungkus
shell A4 bersama. Shell menangani layout print, page-break, footer, dan style
komponen; renderer tetap menangani snapshot serta konten dinamis. Bukti
implementasi tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-10-PREVIEW-AND-PDF.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-10-PREVIEW-AND-PDF.md).

### Step 11 — Tambahkan pengujian

Pengujian minimal:

- CRUD dan permission template;
- template versioning;
- aturan aktivasi dan archive;
- audit log;
- sanitasi HTML berbahaya;
- penolakan placeholder tidak dikenal;
- escaping nilai dinamis;
- snapshot template dan schema pada quotation;
- template baru tidak mengubah quotation lama;
- rendering item dan terms dinamis;
- konsistensi renderer preview dan PDF;
- visual PDF untuk konten pendek, panjang, tabel besar, dan multi-page.

Pengujian SQLite tidak dianggap sebagai bukti PostgreSQL concurrency atau UAT.

**Hasil:** automated test dan visual rendering evidence.

**Status 23 Juli 2026:** selesai. Seluruh kontrak minimum tercakup automated
test, gate real Chrome lulus, dan empat fixture PDF (short, long-text,
table-large, multi-page) telah dirender ke PNG serta diperiksa visual. Bukti dan
temuan perbaikan tersedia di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-11-TESTING-AND-VISUAL-QA.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-11-TESTING-AND-VISUAL-QA.md).

### Step 12 — Migrasikan template quotation existing

Konversi template yang saat ini tersimpan dalam `document_templates.settings`:

- pertahankan schema kolom existing;
- buat HTML versi awal berdasarkan layout quotation saat ini;
- jalankan migration atau manifest dalam mode dry-run;
- aktifkan template hasil migrasi;
- pastikan quotation lama masih dapat dibuka dan dicetak;
- dokumentasikan rollback.

**Hasil:** fitur baru aktif tanpa kehilangan kompatibilitas data lama.

**Status 23 Juli 2026:** selesai pada PostgreSQL lokal. Dry-run menemukan satu
template legacy aktif; apply membuat serta mengaktifkan versi WYSIWYG baru,
sementara lima quotation lama mempertahankan snapshot dan tetap dapat dirender.
Satu quotation lama aktual berhasil dicetak melalui Chrome. Command rollback
teruji automated dan tidak menghapus versi atau snapshot. Bukti lengkap tersedia
di
[`docs/implementation/DOCUMENT-TEMPLATE-STEP-12-LEGACY-MIGRATION.md`](docs/implementation/DOCUMENT-TEMPLATE-STEP-12-LEGACY-MIGRATION.md).

## 4. Urutan Eksekusi

Eksekusi dilakukan satu milestone per satu waktu:

1. Spesifikasi placeholder dan lifecycle template.
2. Database dan model versioning.
3. Permission, policy, dan audit.
4. CRUD template.
5. Integrasi TinyMCE.
6. Placeholder picker dan sanitasi.
7. Renderer template.
8. Integrasi form quotation.
9. Preview dan PDF.
10. Test, visual verification, dan migrasi template existing.

Setiap milestone harus:

- menjaga perubahan tetap dalam scope step tersebut;
- memiliki automated test yang relevan;
- mencatat keputusan atau dokumentasi implementasi;
- membedakan bukti lokal dari UAT/deployment;
- berhenti sebelum melanjutkan ke milestone berikutnya.

## 5. Boundary Eksekusi Pertama

Pekerjaan pertama, **Step 1 — Spesifikasi placeholder dan lifecycle template**, telah selesai.

Step 2 — **Perluas database dan model template** telah selesai.

Step 3 — **Tambahkan permission, policy, dan audit** telah selesai.

Step 4 — **Buat CRUD template quotation** telah selesai.

Step 5 — **Integrasikan TinyMCE lokal** telah selesai.

Step 6 — **Placeholder picker dan validation** telah selesai.

Step 7 — **Sanitasi dan normalisasi HTML** telah selesai dan diverifikasi ulang
sebagai milestone mandiri.

Step 8 — **Buat renderer template** telah selesai.

Step 12 — **Migrasikan template quotation existing** telah selesai pada
PostgreSQL lokal.

Seluruh roadmap document template selesai secara lokal. UAT, production
deployment, dan restore drill backup belum dilakukan.
