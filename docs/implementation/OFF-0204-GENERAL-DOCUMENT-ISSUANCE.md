# OFF-0204 - General Document Number Issuance

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- Halaman `GET /documents/create` menampilkan form penerbitan nomor surat umum untuk user dengan permission `documents.issue`.
- Form hanya memuat tipe dokumen aktif dan menampilkan code serta pola nomor agar operator dapat memeriksa tipe sebelum menerbitkan.
- Input terdiri dari tipe dokumen, judul maksimal 255 karakter, dan peruntukan maksimal 5.000 karakter. Seluruh input divalidasi dan di-trim di server.
- `POST /documents` memanggil `DocumentNumberIssuer` dari OFF-0203 sehingga sequence, nomor, register, dan audit tetap dibuat secara atomic.
- Setelah berhasil, POST menggunakan redirect ke `GET /documents/{document}/issued`. Refresh halaman hasil tidak mengulangi penerbitan.
- Halaman hasil menampilkan nomor dengan teks selectable dan tombol Clipboard API. Bila clipboard browser tidak tersedia, UI mengarahkan pengguna untuk menyalin teks secara manual.
- `DocumentPolicy` membatasi create pada `documents.issue`, pembacaan hasil pada issuer atau user dengan `documents.read`, dan menyiapkan guard `documents.void` untuk fase register berikutnya.
- Empty state mencegah submit ketika belum tersedia tipe aktif. Tipe yang dinonaktifkan setelah form dibuka tetap ditolak oleh validasi server dan guard transactional issuer.

## Batas fase

Daftar register, filter/pencarian, detail audit, dan aksi void tidak ditambahkan pada halaman ini karena merupakan cakupan `OFF-0205`.

## Bukti verifikasi

```text
php artisan test --filter=GeneralDocumentIssuanceTest
6 passed (33 assertions)

php artisan test
50 passed (244 assertions)

php artisan view:cache
Blade templates cached successfully

vendor/bin/pint --test app database routes tests
PASS
```

Feature test membuktikan daftar hanya memuat tipe aktif, penerbitan dan audit berhasil, hasil nomor copyable, input invalid tidak mengonsumsi sequence, permission issue wajib, reader dapat melihat hasil, user dasar ditolak, serta empty state menonaktifkan penerbitan.
