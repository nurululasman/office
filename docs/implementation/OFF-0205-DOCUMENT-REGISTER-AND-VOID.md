# OFF-0205 - Document Register, Audit Detail, and Void

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- `GET /documents` menyediakan register terpaginasikan dengan eager loading tipe, issuer, dan voider.
- Register mendukung pencarian case-insensitive pada nomor, judul, dan peruntukan serta filter tipe, `period_year`, dan status `issued`/`void`. Query filter dipertahankan saat berpindah halaman.
- `GET /documents/{document}` menampilkan nomor, tipe, judul, peruntukan, periode/sequence, metadata penerbitan, metadata void, dan audit trail berurutan.
- Audit detail menampilkan waktu bisnis, actor, action, serta snapshot before/after untuk penerbitan dan void.
- `DocumentPolicy` membatasi register/detail dengan `documents.read` dan aksi void dengan `documents.void`.
- Void membutuhkan alasan 5-2.000 karakter, mengunci row dokumen `FOR UPDATE`, dan memperbarui `voided_at`, `voided_by`, serta `void_reason` dalam satu transaksi.
- Audit `document.voided` berada dalam transaksi yang sama dan menyimpan actor, IP address, user agent, serta snapshot before/after.
- Retry void bersifat idempotent: timestamp dan alasan pertama dipertahankan tanpa audit kedua.
- Void tidak menghapus register, tidak menurunkan sequence, dan tidak membuat nomor dapat digunakan ulang.

## Batas fase

Void pada quotation dan contract kelak harus dijalankan bersama perubahan status entity sumber oleh workflow domain masing-masing pada OFF-0305/OFF-0505. Service register ini menangani invariants record nomor dan dapat dipanggil dari transaksi workflow tersebut.

## Bukti verifikasi

```text
php artisan test --filter=DocumentRegisterAndVoidTest
5 passed (40 assertions)

php artisan test
55 passed (284 assertions)

php artisan view:cache
Blade templates cached successfully

vendor/bin/pint --test app database routes tests
PASS
```

Smoke test rollback-only pada PostgreSQL lokal membuktikan constraint void menerima metadata lengkap, nomor tetap `SMOKE-0001`, status void tersimpan, dan dua audit (issue + void) tersedia di dalam transaksi. Setelah rollback, jumlah user dan tipe smoke sama-sama `0`.

Feature test mencakup kombinasi pencarian/filter, detail audit issuance dan void, permission, validasi alasan, retry idempotent, nomor tidak digunakan ulang, serta pembatasan akses register/detail.
