# Document Template Step 01 — Spesifikasi Placeholder dan Lifecycle

## Status

Selesai sebagai kontrak desain pada 23 Juli 2026.

Step ini hanya menetapkan perilaku sistem. Belum ada perubahan database, model,
route, permission, UI TinyMCE, sanitizer, renderer, atau alur quotation.

## 1. Tujuan

Pengguna berwenang dapat membuat beberapa keluarga template quotation melalui
TinyMCE. Pengguna quotation memilih salah satu versi template yang aktif ketika
membuat draft. Dokumen lama tetap dapat direproduksi walaupun template kemudian
diubah, diganti versinya, atau diarsipkan.

Template adalah HTML terkontrol dengan placeholder allowlist. Template bukan
Blade, PHP, JavaScript, atau sumber data utama quotation.

## 2. Istilah

| Istilah | Definisi |
|---|---|
| Keluarga template | Identitas stabil sebuah desain, misalnya `quotation-full-container-dry`. |
| Versi | Nomor revision monotonik di dalam satu keluarga template. |
| Template draft | Versi yang masih dapat diedit dan belum dapat dipilih pada quotation baru. |
| Template aktif | Versi yang dapat dipilih pada quotation baru. |
| Template archived | Versi read-only yang tidak dapat dipilih pada quotation baru. |
| Placeholder scalar | Token yang menghasilkan teks escaped atau teks terformat. |
| Placeholder struktural | Token yang menghasilkan fragmen HTML dari renderer aplikasi. |
| Snapshot | Salinan immutable kontrak render yang dilekatkan pada quotation. |

## 3. Kontrak Keluarga dan Versi Template

1. Setiap template mempunyai `template_key` stabil dalam format kebab-case,
   misalnya `quotation-full-container-dry`.
2. Nomor versi dimulai dari `1` dan meningkat monotonik dalam satu
   `template_key`.
3. Kombinasi `(type, template_key, version)` harus unik.
4. Banyak keluarga template quotation boleh aktif pada saat yang sama.
5. Hanya satu versi aktif yang diperbolehkan untuk satu kombinasi
   `(type, template_key)`.
6. Membuat versi baru dilakukan dengan menduplikasi versi terakhir atau versi
   yang dipilih menjadi draft baru. Versi sumber tidak berubah.
7. Aktivasi versi draft dilakukan secara atomik:
   - versi draft menjadi `active`;
   - versi aktif sebelumnya dalam keluarga yang sama menjadi `archived`;
   - quotation yang sudah memakai versi lama tidak berubah.
8. Template archived bersifat read-only. Perbaikan dilakukan dengan membuat
   versi draft baru, bukan membuka kembali atau mengubah record archived.
9. Template yang telah direferensikan quotation atau generated file tidak boleh
   dihapus permanen.
10. Setidaknya satu template quotation aktif harus tersedia agar pengguna dapat
    membuat quotation baru.

## 4. Kontrak Konten Template

Satu versi template menyimpan kontrak berikut:

| Bagian | Fungsi |
|---|---|
| `content_html` | HTML hasil TinyMCE yang sudah disanitasi. |
| `item_schema` | Definisi kolom item dinamis, termasuk key, label, tipe, posisi, group, width, align, dan required. |
| `default_intro_text` | Nilai awal pengantar yang dapat disunting pada draft quotation. |
| `default_closing_text` | Nilai awal penutup yang dapat disunting pada draft quotation. |
| `default_terms` | Daftar terms awal yang dapat disunting dan diurutkan pada draft quotation. |
| `editor_config` | Opsi editor yang tervalidasi; bukan konfigurasi JavaScript bebas. |

`settings.columns` yang digunakan implementasi saat ini akan dipetakan ke
`item_schema` pada Step 2 tanpa mengubah arti key atau tipe nilainya.

## 5. Sintaks Placeholder

1. Sintaks canonical adalah `{{ placeholder_name }}`.
2. Nama placeholder hanya memakai huruf kecil, angka, dan underscore serta
   harus diawali huruf.
3. Spasi di antara brace dan nama dinormalisasi oleh server.
4. Placeholder bersifat case-sensitive.
5. Placeholder tidak menerima ekspresi, filter, argumen, property access,
   loop, kondisi, atau kode.
6. Token yang tidak berada dalam allowlist menyebabkan template gagal disimpan
   atau diaktifkan.
7. Renderer hanya memproses token pada text node. Placeholder tidak boleh
   digunakan sebagai nama tag, nama atribut, nilai URL, CSS, atau potongan
   sintaks HTML.

Contoh valid:

```text
{{ customer_name }}
{{ quotation_items }}
```

Contoh tidak valid:

```text
{{ Customer_Name }}
{{ customer.name }}
{{ customer_name|raw }}
{{ include('file') }}
```

## 6. Allowlist Placeholder Scalar

Semua placeholder scalar menghasilkan teks yang di-escape. Line break pada data
multiline hanya diubah menjadi elemen presentasi aman oleh renderer.

| Placeholder | Sumber | Format ketika kosong |
|---|---|---|
| `quotation_number` | Nomor document quotation | `DRAFT — nomor belum terbit` pada preview draft |
| `quotation_date` | `quotation_date` | Tidak boleh kosong |
| `subject` | `subject` | Tidak boleh kosong |
| `customer_name` | Snapshot customer | Tidak boleh kosong |
| `customer_address` | Snapshot customer | Opsional; string kosong bila tidak diisi |
| `attention_name` | Snapshot customer | String kosong |
| `attention_role` | Snapshot customer | String kosong |
| `sender_name` | Snapshot sender | Tidak boleh kosong |
| `sender_title` | Snapshot sender | Opsional; string kosong bila tidak diisi |
| `currency` | Kode mata uang quotation | Tidak boleh kosong |
| `intro_text` | Nilai draft quotation | String kosong |
| `closing_text` | Nilai draft quotation | String kosong |
| `company_legal_name` | Snapshot company profile | Tidak boleh kosong |
| `company_display_name` | Snapshot company profile | Tidak boleh kosong |
| `company_address` | Snapshot company profile | String kosong |
| `company_email` | Snapshot company profile | String kosong |
| `company_phone` | Snapshot company profile | String kosong |
| `company_website` | Snapshot company profile | String kosong |

Tanggal diformat menggunakan timezone bisnis dan locale aplikasi. Nilai uang di
dalam item tetap diformat oleh `QuotationValueFormatter` berdasarkan
`item_schema`, bukan oleh template.

## 7. Allowlist Placeholder Struktural

Placeholder struktural tidak menerima HTML dari quotation. Renderer aplikasi
membangun fragmen HTML dari data tervalidasi dan partial yang dimiliki aplikasi.

| Placeholder | Fungsi | Batas |
|---|---|---|
| `company_logo` | Logo dari snapshot company profile/private asset yang disetujui | Maksimal satu |
| `quotation_items` | Tabel item dari snapshot schema dan item quotation | Tepat satu |
| `quotation_terms` | Daftar terms berdasarkan posisi | Maksimal satu |
| `signature_block` | Blok sender dan area persetujuan cetak | Maksimal satu |
| `draft_watermark` | Watermark khusus preview draft | Maksimal satu |

Aturan tambahan:

1. `quotation_items` wajib ada tepat satu kali.
2. `company_logo`, `quotation_terms`, `signature_block`, dan `draft_watermark`
   boleh tidak digunakan karena beberapa desain dapat menempatkannya pada shell
   yang dikendalikan aplikasi.
3. Placeholder struktural harus menjadi satu-satunya isi di dalam block
   container-nya. Contoh yang diterima:

   ```html
   <div>{{ quotation_items }}</div>
   ```

4. Placeholder struktural tidak boleh berada di dalam link, heading, table
   buatan editor, atau elemen inline.
5. HTML hasil placeholder struktural selalu dianggap milik renderer aplikasi;
   pengguna tidak dapat memasukkan markup penggantinya.

## 8. Placeholder Minimum untuk Aktivasi

Template draft dapat disimpan walaupun belum lengkap. Aktivasi hanya diizinkan
bila template memiliki:

- tepat satu `quotation_items`;
- `quotation_date`;
- `subject`;
- `customer_name`;
- `sender_name`;
- `quotation_number`.

`company_display_name` atau `company_legal_name` wajib ada sekurang-kurangnya
satu. `quotation_terms`, `intro_text`, `closing_text`, dan data attention
bersifat opsional.

Validasi minimum dilakukan kembali saat aktivasi meskipun template sudah
divalidasi ketika disimpan.

## 9. Perilaku Field yang Dapat Disunting pada Quotation

1. `default_intro_text`, `default_closing_text`, dan `default_terms` disalin
   ketika draft baru dibuat.
2. Pengguna quotation dapat mengubah nilai hasil salinan selama status quotation
   masih dapat diedit.
3. Perubahan default pada template tidak mengubah nilai pada draft yang sudah
   dibuat.
4. Isi item mengikuti snapshot `item_schema` dari template yang dipilih.
5. Mengganti template pada form sebelum draft pertama disimpan mengganti default
   dan schema form.
6. Setelah draft tersimpan, template tidak dapat diganti in-place. Jika
   diperlukan pada masa depan, perpindahan template harus menjadi aksi eksplisit
   dengan preview, pemetaan data, konfirmasi, lock version, dan audit tersendiri.

## 10. Waktu dan Isi Snapshot

Snapshot dibuat di dalam transaksi yang sama dengan pembuatan quotation pertama.
Snapshot minimal mencakup:

- `template_id`;
- `template_key`;
- `template_version`;
- HTML template yang sudah disanitasi;
- checksum HTML;
- item schema;
- default yang sudah disalin menjadi nilai quotation;
- identitas dan branding company profile yang diperlukan renderer;
- versi kontrak placeholder/renderer.

Aturan snapshot:

1. Preview draft selalu menggunakan snapshot quotation, bukan membaca konten
   terbaru dari template aktif.
2. Edit dan revisi quotation rejected mempertahankan snapshot yang sama.
3. Completion/approval tidak mengambil ulang template atau company profile.
4. PDF final menggunakan snapshot yang sama dan mencatat `template_id`.
5. Regenerasi PDF memakai snapshot quotation yang sama, sehingga hasil tidak
   berubah hanya karena template aktif sudah berganti.
6. Template archived tetap dapat digunakan untuk preview dan regenerasi
   quotation yang sudah mereferensikannya.

## 11. Lifecycle dan Transisi

```text
create/duplicate
      |
      v
    draft -------- activate --------> active
      |                                 |
      | archive                         | activate newer version
      v                                 v
   archived <------------------------ archived
```

| Dari | Aksi | Ke | Ketentuan |
|---|---|---|---|
| Tidak ada | Create | `draft` | Permission create dan `template_key` valid |
| `draft` | Update | `draft` | Optimistic locking dan audit |
| `draft` | Activate | `active` | Kontrak lengkap, sanitized, preview lulus |
| `draft` | Archive | `archived` | Tidak tersedia untuk quotation baru |
| `active` | Activate newer version | `archived` | Dilakukan atomik bersama aktivasi versi baru |
| `active` | Archive | `archived` | Ditolak bila menyebabkan tidak ada template quotation aktif |
| `archived` | Update/Activate | Ditolak | Buat versi draft baru |

Tidak ada transisi kembali dari `archived`.

## 12. Perilaku Concurrency

1. Update draft menggunakan `lock_version`; stale update ditolak.
2. Penentuan versi baru dan aktivasi harus dilakukan dalam transaksi database.
3. Baris keluarga/versi terkait dikunci ketika menentukan nomor versi atau
   mengganti versi aktif.
4. Unique constraint database tetap menjadi pertahanan terakhir terhadap dua
   versi aktif atau nomor versi duplikat.
5. Kegagalan aktivasi tidak boleh meninggalkan keluarga tanpa versi aktif yang
   sebelumnya sudah tersedia.
6. Bukti concurrency final harus dijalankan pada PostgreSQL; SQLite bukan bukti
   row-lock atau partial-unique behavior.

## 13. Aturan Preview

Template draft harus dapat dipreview dengan fixture data yang ditandai jelas
sebagai sample dan bukan quotation resmi.

Preview sebelum aktivasi wajib memeriksa:

- placeholder minimum;
- placeholder unknown atau duplikat struktural;
- HTML hasil sanitasi;
- item schema valid;
- tabel tanpa kolom;
- konten satu halaman;
- konten panjang dan multi-page;
- absence of clipping, overlap, halaman kosong tak disengaja, serta asset gagal.

Preview bukan approval bisnis dan tidak menerbitkan nomor quotation.

## 14. Keamanan

1. Nilai scalar selalu di-escape.
2. Tidak ada mode placeholder `raw`.
3. HTML TinyMCE disanitasi pada server saat save dan divalidasi ulang saat
   activate.
4. Tag aktif seperti `script`, `iframe`, `object`, `embed`, `form`, `input`,
   `button`, `video`, dan `audio` tidak diizinkan.
5. Event handler, inline JavaScript, `javascript:` URL, external stylesheet,
   remote font, dan remote image tidak diizinkan.
6. Style hanya berasal dari subset yang nanti ditetapkan sanitizer; CSS halaman,
   `@page`, fixed positioning, dan z-index bebas tidak dapat ditulis pengguna.
7. Asset gambar berasal dari company profile atau media private yang mempunyai
   allowlist MIME, batas ukuran, dan checksum.
8. Content Security Policy browser tetap berlaku; sanitizer bukan pengganti CSP.

## 15. Audit Minimum

Audit log harus mencatat:

- `quotation_template.created`;
- `quotation_template.updated`;
- `quotation_template.version_created`;
- `quotation_template.activated`;
- `quotation_template.archived`.

Audit menyimpan actor, template ID/key/version, before/after yang relevan,
timestamp, request metadata, dan checksum konten. Audit tidak perlu menyimpan
salinan HTML penuh bila record versi immutable sudah menyediakannya.

## 16. Acceptance Criteria Step 1

- [x] Keluarga template dan aturan versioning didefinisikan.
- [x] Status serta seluruh transisi lifecycle didefinisikan.
- [x] Aturan satu versi aktif per keluarga dan banyak keluarga aktif ditetapkan.
- [x] Allowlist placeholder scalar dan struktural ditetapkan.
- [x] Placeholder minimum untuk aktivasi ditetapkan.
- [x] Aturan default intro, closing, dan terms ditetapkan.
- [x] Waktu serta isi snapshot quotation ditetapkan.
- [x] Perilaku draft, completion, PDF, dan regenerasi terhadap snapshot ditetapkan.
- [x] Aturan concurrency, preview, security, dan audit ditetapkan.
- [x] Scope Step 2 dapat diturunkan tanpa mengubah kontrak bisnis Step 1.

## 17. Out of Scope

Step ini tidak:

- membuat atau menjalankan migration;
- mengubah model Eloquent;
- menambah route, controller, request, policy, atau permission;
- mengaktifkan TinyMCE;
- memilih library sanitizer;
- membuat parser placeholder atau renderer;
- mengubah preview/PDF existing;
- memigrasikan template existing;
- membuktikan UAT atau deployment.

## 18. Input untuk Step 2

Step 2 harus merancang persistence untuk:

- `template_key`, version per keluarga, dan lifecycle status;
- optimistic locking;
- HTML sanitized dan checksum;
- item schema serta editor config;
- default intro, closing, dan terms;
- actor/timestamp lifecycle;
- snapshot render pada quotation;
- constraint satu versi aktif per keluarga yang kompatibel dengan PostgreSQL;
- migrasi aman dari `settings` dan `is_active` existing.
