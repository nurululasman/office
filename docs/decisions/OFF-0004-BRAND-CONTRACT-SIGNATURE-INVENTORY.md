# OFF-0004 - Inventaris Brand, Kontrak, Tanda Tangan, dan Materai

- Status: Accepted, amended
- Tanggal: 2026-07-18
- Perubahan terakhir: 2026-07-18 - logo JBLU ditemukan dan tanda tangan dipastikan manual setelah cetak
- Sumber utama: `D:\MAMAN\YAHER\QUOTATION DEPO PEINITIPAN JBLU(1).pdf`

## Hasil pemeriksaan sumber

Lampiran quotation adalah scan satu halaman berukuran mendekati A4 (`589.68 x 835.92 pt`), dibuat oleh `Scanner System`, dan tidak memiliki text layer. Informasi berikut diperoleh melalui inspeksi visual, bukan OCR otomatis.

Repository Office memiliki logo PT Jayabaru Logistik Utama pada `public/static/jblu.png`. Asset telah diperiksa secara visual dan sesuai identitas pada quotation: ilustrasi kapal, truk, pesawat, nama perusahaan, dan tagline `Connecting Logistics`.

Metadata asset:

| Properti | Nilai |
|---|---|
| Path | `public/static/jblu.png` |
| Format | PNG, 32-bit ARGB |
| Dimensi | 896 x 755 px |
| Ukuran | 734362 bytes |
| SHA-256 | `CF7F4C45F4D23C345E35D17A02758D92CD644E2FFE222F23EFE60F025A14DBCC` |

Asset ini menjadi logo resmi template saat ini. `public/static/depo_os_logo.png` tetap merupakan logo brand Depo OS, sedangkan `public/static/logo*.svg` adalah asset template UI; keduanya dilarang digunakan sebagai logo JBLU.

Logo pada scan hanya menjadi referensi posisi. Template wajib memakai `public/static/jblu.png`, bukan crop dari scan.

## Identitas perusahaan yang terlihat

| Field | Nilai pada scan | Status penggunaan |
|---|---|---|
| Legal/display name | `PT. JAYABARU LOGISTIK UTAMA` | Dapat menjadi draft, wajib verifikasi legal sebelum production |
| Company code | `JBLU` | Diterima melalui keputusan penomoran `OFF-0002` |
| Address line 1 | `Jl. Menteng Metropolitan (MM) Blok D7/27` | Draft, wajib verifikasi |
| Address line 2 | `Ujung Menteng Cakung Jakarta Timur` | Draft, wajib verifikasi |
| City/postal/country | `Jakarta 13960, Indonesia` | Draft, wajib verifikasi |
| Email | `ardhian.widyanto@jayabaru-logistics.com` | Email personal pengirim; jangan diasumsikan sebagai email korporat umum |
| Tagline | `Connecting Logistics` | Terlihat di logo; ejaan/capitalization wajib diverifikasi dari brand asset asli |
| Phone | Tidak terlihat | Belum tersedia |
| Website | Tidak terlihat | Belum tersedia |
| NPWP/tax ID | Tidak terlihat | Belum tersedia |

Data yang belum terverifikasi boleh dipakai pada development preview dengan label konfigurasi draft, tetapi template production harus fail closed bila profil perusahaan belum disetujui.

## Verifikasi asset dan identitas

Logo untuk implementasi sudah tersedia. Sebelum `OFF-0401` dinyatakan selesai:

- render A4 harus memverifikasi ketajaman, rasio, whitespace, dan warna `public/static/jblu.png` pada hasil cetak;
- bila tersedia kemudian, SVG resmi dapat menggantikan PNG melalui versi asset baru tanpa menimpa snapshot dokumen lama;
- legal name, alamat, email umum, telepon, website, dan tax ID yang boleh dicetak;
- pedoman warna dan minimum clear space/ukuran logo dapat ditambahkan bila tersedia.

Asset disimpan melalui storage terkontrol dan dicatat dengan SHA-256. Template final mereferensikan asset version, bukan URL eksternal.

## Inventaris layout quotation

### Header

- identitas perusahaan berada di kiri atas;
- logo berada di kanan atas;
- garis horizontal biru memisahkan header dan isi;
- tidak terlihat nomor halaman/footer pada dokumen satu halaman.

### Metadata

- kiri: Quotation, To, nama/peran attention, Subject;
- kanan: Date dan From;
- nomor contoh `QT-JBLU-2025080118`;
- tanggal ditampilkan dalam bahasa Inggris sebagai `Agustus 28th 2025`; implementasi harus memilih locale yang konsisten dan tidak menyalin campuran bahasa tersebut tanpa keputusan template.

### Isi

- salam penerima dan kalimat pengantar;
- tabel header bertingkat dengan No, Description, kelompok Full Container (Dry) 20'/40', dan Note;
- dua item contoh: LOLO dan Storage/day;
- terms berupa bullet: TOP dan VAT;
- paragraf ucapan terima kasih serta penutup.

Kolom item adalah konfigurasi template key-value sesuai revisi arsitektur; 20'/40' bukan schema tetap.

## Tanda tangan quotation

Scan memperlihatkan dua blok:

| Sisi | Label | Nama pada contoh | Catatan |
|---|---|---|---|
| JBLU | `Sincerely Yours,` | Ardhian Widyanto | Jabatan tidak terlihat |
| Customer | `Approved By,` | Vina Louise | Terdapat tanda tangan dan stempel perusahaan customer |

Keputusan versi pertama:

- PDF final adalah dokumen siap cetak yang hanya menyediakan nama tercetak dan ruang tanda tangan/stempel basah;
- seluruh tanda tangan dilakukan manual setelah PDF dicetak;
- tidak ada upload, penyisipan, atau validasi gambar tanda tangan digital;
- tanda tangan dan stempel PT Global Link Indonesia pada scan adalah data dokumen lama dan dilarang disalin ke quotation baru;
- sender name/title dan customer approver name/title disimpan sebagai snapshot per quotation;
- customer approver boleh kosong ketika quotation diterbitkan, karena biasanya diisi saat customer menyetujui;
- aplikasi tidak melacak status kertas sudah ditandatangani pada MVP.

## Inventaris kontrak dan pasal

Tidak ada contoh kontrak di repository maupun lampiran yang diberikan. Karena itu belum ada dasar yang sah untuk menetapkan:

- judul dan bentuk legal kontrak;
- recital/pembukaan;
- identitas dan kapasitas hukum para pihak;
- daftar pasal, klausul harga, SLA, liability, force majeure, termination, dispute, dan governing law;
- jumlah serta posisi blok tanda tangan;
- kebutuhan paraf per halaman;
- footer, nomor halaman, lampiran, dan jadwal tarif;
- redaksi atau posisi materai.

`OFF-0501` tidak boleh selesai hanya dengan template generik buatan developer. Minimal satu contoh kontrak resmi yang boleh dijadikan acuan dan persetujuan pemilik bisnis wajib tersedia.

## Kebijakan materai versi awal

- Aplikasi tidak mengklaim melakukan e-meterai atau validasi legal materai.
- Template kontrak menyediakan placeholder materai yang dapat diaktifkan/nonaktifkan melalui `document_templates.settings`.
- Placeholder hanya berupa area/layout, bukan gambar materai.
- Nominal, pihak yang membubuhkan, jumlah rangkap, dan posisi final harus berasal dari template kontrak yang disetujui.
- Quotation tidak menggunakan placeholder materai secara default.
- Tanda tangan, stempel, dan materai dibubuhkan manual setelah PDF dicetak; tidak ada penyimpanan gambarnya pada MVP.

Kebijakan ini meminimalkan data biometrik/sensitif dan mencegah aplikasi mereproduksi bukti persetujuan tanpa otorisasi.

## Model konfigurasi yang dibutuhkan

`company_profiles` menyimpan identitas dan referensi logo terverifikasi. `document_templates.settings` menyimpan layout versioned, antara lain:

```json
{
  "signature_blocks": [
    {"side": "company", "label": "Sincerely Yours", "show_title": true},
    {"side": "customer", "label": "Approved By", "show_title": true}
  ],
  "wet_signature_space_mm": 30,
  "stamp_space": true,
  "stamp_duty_placeholder": false
}
```

Nilai di atas adalah bentuk konfigurasi, bukan data tanda tangan aktual. Quotation/contract menyimpan snapshot nama, jabatan, dan template version ketika complete.

## Gate implementasi

### Gate `OFF-0401` quotation PDF

- `public/static/jblu.png` digunakan dan tidak terdistorsi;
- asset hash cocok dengan inventaris atau perubahan asset dibuat sebagai versi baru;
- visual proof dibandingkan dengan scan;
- customer stamp/signature lama tidak muncul pada output baru.

### Gate `OFF-0501` contract

- contoh kontrak resmi diterima;
- daftar pasal serta field dinamis disetujui;
- signature blocks dan kebutuhan materai disetujui;
- relasi satu quotation ke nol atau banyak kontrak dipertahankan sesuai arsitektur; template kontrak tidak boleh mengubah cardinality tersebut secara implisit.

## Acceptance criteria OFF-0004

- [x] Identitas perusahaan yang tampak pada quotation telah diinventarisasi dan status verifikasinya dicatat.
- [x] Logo JBLU `public/static/jblu.png` ditemukan, diperiksa visual, dan metadata/hash dicatat.
- [x] Asset UI/Depo OS ditandai bukan logo JBLU.
- [x] Struktur quotation dan signature blocks dicatat dari inspeksi visual.
- [x] Tanda tangan/stempel lama dinyatakan tidak boleh digunakan ulang.
- [x] Ketiadaan contoh kontrak/pasal dicatat sebagai gate `OFF-0501`.
- [x] Kebijakan MVP ditetapkan: PDF siap cetak; tanda tangan, stempel, dan materai dilakukan manual setelah dicetak tanpa penyimpanan gambar.
