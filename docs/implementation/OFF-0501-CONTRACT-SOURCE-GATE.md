# OFF-0501 - Contract source and business approval gate

## Status

**Blocked - belum boleh ditandai selesai.** OFF-0501 mensyaratkan sekurang-kurangnya satu contoh kontrak resmi yang boleh dijadikan acuan dan persetujuan pemilik bisnis. Template generik buatan developer tidak memenuhi gate yang ditetapkan pada `OFF-0004`.

## Audit sumber 18 Juli 2026

Pencarian dilakukan pada repository Office dan `D:\MAMAN\YAHER`, dengan mengecualikan dependency/build output. Kandidat yang ditemukan:

| Sumber | Hasil pemeriksaan | Keputusan |
| --- | --- | --- |
| `D:\MAMAN\YAHER\logistics park.docx` | DOCX hanya memuat satu floating image logo JBLU 704 x 592 px; tidak memiliki isi kontrak, pasal, tabel, header/footer, atau signature blocks | Bukan contoh kontrak |
| `QUOTATION DEPO PEINITIPAN JBLU(1).pdf` | Contoh quotation yang telah digunakan pada Fase 4 | Bukan kontrak |
| `OTHERS\INVOICE JBLU 001.pdf` | Invoice | Bukan kontrak |
| `OTHERS\PARTNERSHIP PROPOSAL*`, `Partnership.docx`, dan `PROPOSAL PENAWARAN*` | Proposal/penawaran, bukan dokumen kontrak JBLU yang disetujui | Tidak boleh dijadikan template legal |
| Dokumen regulasi/manual pada `OTHERS` | Referensi eksternal yang tidak menetapkan bentuk kontrak JBLU | Bukan contoh kontrak |

Metadata kandidat utama:

- SHA-256 `logistics park.docx`: `4C8800AE39E790BF4AC69A4140FFBF4663C4F21B03DA319A3C77C560EBB3A2E9`;
- embedded `word/media/image1.png`: 704 x 592 px, SHA-256 `1C5816AF77BE38797707B23B784CB83F4C96C700779A259979D0F8F56EB0B42F`;
- OOXML hanya memiliki empat paragraph kosong, tanpa table dan tanpa inline shape; teks objek yang tersisa hanya `LOGISTICS PARK`.

LibreOffice tidak tersedia pada runtime sehingga DOCX tidak dapat dirender melalui gate standar Documents. Namun, image tertanam telah diekstrak dan diperiksa visual; hasilnya murni logo PT Jayabaru Logistik Utama. Pemeriksaan struktur OOXML dan `python-docx` memastikan tidak ada isi kontrak tersembunyi pada body, table, header, atau footer.

## Input minimum untuk membuka gate

Pemilik bisnis/legal perlu memberikan satu file DOCX atau PDF resmi yang boleh dijadikan acuan dan mengonfirmasi:

1. jenis/judul kontrak yang menjadi scope MVP;
2. urutan pembukaan, recital, definisi, dan pasal yang wajib dipertahankan;
3. identitas legal kedua pihak dan kapasitas penandatangan;
4. struktur scope layanan, lokasi, jangka waktu, harga, pajak, pembayaran, SLA, dan lampiran;
5. klausul tanggung jawab, force majeure, kerahasiaan, penghentian, pemberitahuan, perubahan, hukum, serta penyelesaian sengketa;
6. jumlah signature blocks, urutan pihak, kebutuhan saksi/paraf/stempel, dan ruang tanda tangan basah;
7. apakah placeholder materai aktif, pihak pembubuh, posisi, nominal, dan jumlah rangkap;
8. format nomor halaman, footer, lampiran, dan schedule tarif;
9. aturan tanggal berlaku serta transisi status yang disetujui;
10. apakah kontrak dari quotation membawa seluruh item harga atau hanya ringkasan/lampiran.

## Keputusan yang belum boleh dikunci

Sampai sumber resmi dan approval bisnis diterima, hal berikut tetap provisional dan tidak boleh diterjemahkan menjadi migration/template production:

- daftar serta redaksi pasal;
- field dinamis dan kebutuhan `contract_items`;
- layout signature, paraf, stempel, dan materai;
- transisi `complete` ke `active`/`expired` serta pemicu tanggal atau konfirmasi manual;
- hubungan isi utama dengan lampiran/schedule tarif;
- aturan satu atau beberapa kontrak dari satu quotation.

Keputusan arsitektur yang tetap berlaku adalah snapshot dari quotation, cardinality satu quotation ke nol atau banyak kontrak, PDF siap cetak, dan tanda tangan/stempel/materai manual tanpa penyimpanan gambar.

## Exit criteria OFF-0501

- [ ] Contoh kontrak resmi diterima dan hash sumber dicatat.
- [ ] Seluruh halaman diperiksa; untuk DOCX dilakukan render visual bila LibreOffice tersedia.
- [ ] Struktur pasal dan field dinamis diturunkan dari sumber, bukan asumsi developer.
- [ ] Signature blocks dan kebijakan materai disetujui pemilik bisnis/legal.
- [ ] State machine kontrak dan aturan tanggal disetujui.
- [ ] Keputusan final dicatat sebagai amendment `OFF-0004` dan roadmap OFF-0501 baru dicentang.
