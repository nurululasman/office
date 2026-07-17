# OFF-0002 - Format Penomoran Dokumen

- Status: Accepted, amended
- Tanggal: 2026-07-17
- Perubahan terakhir: 2026-07-17 - format digeneralisasi agar setiap tipe dapat memiliki susunan berbeda
- Bukti legacy: `QUOTATION DEPO PEINITIPAN JBLU(1).pdf`

## Keputusan utama

Setiap tipe dokumen memiliki format sendiri. Format bukan satu pola global. Contoh pola yang valid:

```text
QT-JBLU-{YYYY}{MM}{SEQ:4}
PREFIX1-PREFIX2-{SEQ:4}
{SEQ:4}/JBLU/{MONTH_ROMAN}/{YYYY}
```

Prefix, suffix, company code, separator, dan teks tetap disimpan sebagai literal dalam `document_types.number_pattern`. Token hanya digunakan untuk nilai dinamis. Dengan pendekatan ini suatu dokumen dapat memiliki dua prefix, tidak memiliki company code, atau tidak menampilkan tahun sama sekali tanpa perubahan schema.

Nomor contoh `QT-JBLU-2025080118` diuraikan sebagai berikut:

| Bagian | Nilai | Arti |
|---|---|---|
| literal | `QT-JBLU-` | Prefix tipe dan identitas perusahaan untuk template quotation ini |
| `{YYYY}` | `2025` | Tahun penerbitan dalam zona `Asia/Jakarta` |
| `{MM}` | `08` | Bulan penerbitan, Agustus |
| `{SEQ:4}` | `0118` | Nomor urut ke-118 pada tipe Quotation untuk tahun 2025 |

Tidak ada separator antara tahun, bulan, dan sequence agar hasil tetap kompatibel dengan contoh legacy. Walaupun bulan tampil di nomor, sequence **tidak reset setiap bulan**. Reset hanya terjadi ketika `period_year` berubah.

## Contoh format per tipe

| Code | Nama | Pattern | Contoh pertama Januari 2027 |
|---|---|---|---|
| `QUOTATION` | Quotation | `QT-JBLU-{YYYY}{MM}{SEQ:4}` | `QT-JBLU-2027010001` |
| `CONTRACT` | Kontrak | `CTR-JBLU-{YYYY}{MM}{SEQ:4}` | `CTR-JBLU-2027010001` |
| User-defined A | Dokumen dua prefix | `PREFIX1-PREFIX2-{SEQ:4}` | `PREFIX1-PREFIX2-0001` |
| User-defined B | Surat formal | `{SEQ:4}/JBLU/{MONTH_ROMAN}/{YYYY}` | `0001/JBLU/I/2027` |

Prefix `CTR` hanya default awal kontrak dan dapat diganti sebelum tipe dipakai. Karena tipe surat dibuat pengguna, tidak ada satu prefix global `SURAT`. Literal seperti `PREFIX1` dan `PREFIX2` menjadi bagian konfigurasi pola tipe tersebut.

Administrator dapat memilih pola lain dari token allowlist untuk tipe baru, tetapi seed Quotation dan Contract menggunakan pola di atas. Perubahan pola hanya memengaruhi nomor berikutnya dan tidak memformat ulang dokumen yang sudah terbit.

## Sumber waktu dan periode

- `issued_at` ditentukan server saat transaksi penerbitan berhasil.
- Bila digunakan, `{YYYY}`, `{YY}`, `{MM}`, dan `{MONTH_ROMAN}` dihitung dari `issued_at` pada zona `Asia/Jakarta`.
- Tanggal dokumen yang diketik pengguna tidak menentukan periode sequence.
- `period_year = issued_at.year` menjadi partition sequence.
- `period_year` tetap disimpan meskipun pattern tidak menampilkan tahun.
- Backdate nomor tidak didukung pada versi pertama.
- Request yang melewati tengah malam/tahun menggunakan waktu ketika row sequence dikunci di dalam transaksi, bukan waktu ketika form dibuka.

## Scope sequence

Sequence terpisah berdasarkan:

```text
document_type_id + period_year
```

Konsekuensinya:

- Quotation dan Contract mempunyai sequence masing-masing.
- Dua tipe surat dengan prefix berbeda mempunyai sequence masing-masing.
- Semua pengguna/cabang PT JBLU berbagi sequence tipe yang sama.
- Sequence belum dipisah per cabang karena nomor contoh hanya menunjukkan satu legal entity `JBLU`.
- Jika legal entity baru ditambahkan, `company_code` harus menjadi bagian key sequence dan unique constraint melalui keputusan/migration baru.

## Padding dan batas

- `{SEQ:4}` menghasilkan minimum empat digit: `0001` sampai `9999`.
- Padding adalah lebar minimum, bukan batas nilai. Sequence `10000` tetap dirender `10000` dan tidak boleh wrap menjadi `0000`.
- Sistem memberi warning operasional mulai sequence `9000`, tetapi tetap dapat menerbitkan nomor.
- Literal dapat memuat huruf, angka, spasi terbatas, titik, garis bawah, slash, dan hyphen. Control character, markup, kurung kurawal literal, serta karakter path traversal ditolak.

## Penerbitan, void, dan gap

- Nomor hanya dianggap terbit setelah record `documents` berhasil dibuat dalam transaksi.
- Draft quotation/contract tidak memesan nomor.
- Quotation dan contract memperoleh nomor ketika transisi ke `complete` berhasil.
- Nomor umum diperoleh ketika pengguna mengonfirmasi penerbitan setelah mengisi tipe, judul, dan peruntukan.
- Nomor yang sudah terbit tidak boleh diedit, dihapus, atau dipakai ulang.
- Void mempertahankan nomor dan alasan di register.
- Gap karena transaksi yang benar-benar rollback tidak terjadi karena increment dan insert berada dalam transaksi yang sama.
- Gap akibat nomor yang kemudian di-void diperbolehkan dan dapat dijelaskan melalui audit log.

## Validasi pattern

Pattern harus:

- mengandung tepat satu `{SEQ:n}` dengan `n` antara 1 dan 10;
- boleh tidak mengandung token tahun; reset tahunan tetap dijalankan menggunakan `period_year` internal;
- hanya menggunakan token `{YYYY}`, `{YY}`, `{MM}`, `{MONTH_ROMAN}`, dan `{SEQ:n}`;
- memiliki literal/token yang disusun melalui segment builder, bukan expression atau kode yang dapat dieksekusi;
- menghasilkan preview yang lolos batas panjang kolom `documents.number`;
- menampilkan warning jika berpotensi sama dengan format tipe lain atau sama kembali setelah reset tahun.

Unique database menjadi perlindungan terakhir pada `(document_type_id, period_year, sequence_value)` dan `(document_type_id, period_year, number)`. `documents.number` tidak unique secara global: pola tanpa tahun dapat menghasilkan `PREFIX1-PREFIX2-0001` lagi pada tahun berikutnya. Register membedakannya menggunakan UUID, tipe dokumen, dan `period_year`.

Administrator harus menerima warning eksplisit ketika menyimpan pola tanpa `{YYYY}`/`{YY}` karena nomor yang terlihat dapat berulang lintas tahun. Warning tidak memblokir penyimpanan karena format tersebut merupakan kebutuhan yang sah.

## Cutover data legacy

Angka `0118` pada contoh membuktikan quotation 2025 pernah mencapai sequence 118, tetapi tidak boleh dipakai sebagai nilai awal tahun deployment tanpa rekonsiliasi register terbaru.

Sebelum production go-live:

1. ekspor register nomor terakhir per tipe dan tahun dari sumber legacy;
2. verifikasi nomor terbesar dan seluruh nomor void;
3. isi `document_sequences.last_value` dengan nilai terakhir yang sudah sah;
4. import nomor lama ke `documents` bila audit historis masuk scope, atau simpan file rekonsiliasi terkontrol;
5. dua pihak (pemilik proses dan administrator) menyetujui nilai cutover;
6. lakukan dry-run untuk memastikan preview nomor berikutnya benar;
7. hentikan penerbitan pada sistem lama sebelum membuka Office production.

Nilai awal tidak boleh ditentukan hanya dari `MAX(number)` tanpa parsing dan pemeriksaan tipe/tahun.

## Contoh acceptance

| Kondisi | Hasil |
|---|---|
| Quotation ke-118, issued 2025-08-28 | `QT-JBLU-2025080118` |
| Quotation berikutnya di September 2025 | `QT-JBLU-2025090119` |
| Contract pertama di September 2025 | `CTR-JBLU-2025090001` |
| Quotation pertama pada 2026-01-01 | `QT-JBLU-2026010001` |
| Quotation kedua pada 2026-12-31 | `QT-JBLU-2026120002` |
| Quotation pertama pada 2027-01-01 | `QT-JBLU-2027010001` |
| Dokumen pola `PREFIX1-PREFIX2-{SEQ:4}`, pertama tahun 2027 | `PREFIX1-PREFIX2-0001` |
| Dokumen pola yang sama, pertama tahun 2028 | `PREFIX1-PREFIX2-0001` dengan `period_year=2028` |

## Acceptance criteria OFF-0002

- [x] Nomor contoh legacy telah diuraikan tanpa mengubah tampilannya.
- [x] Format quotation, contract, dan tipe surat general ditetapkan per tipe, bukan secara global.
- [x] Pattern mendukung banyak prefix/literal dan tidak mewajibkan token tahun.
- [x] Bulan dan tahun berasal dari waktu penerbitan server `Asia/Jakarta`.
- [x] Sequence ditetapkan per tipe per tahun dan reset hanya tahunan.
- [x] Aturan draft, complete, void, gap, serta overflow ditetapkan.
- [x] Prosedur cutover legacy ditetapkan tanpa menebak nomor production berikutnya.
