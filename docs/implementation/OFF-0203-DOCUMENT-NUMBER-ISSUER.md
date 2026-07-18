# OFF-0203 - Transactional Document Number Issuer

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- `DocumentNumberIssuer` menerbitkan nomor, memperbarui sequence, membuat register `documents`, dan menulis audit dalam satu transaksi database.
- Waktu penerbitan berasal dari server dalam zona bisnis `Asia/Jakarta`; timestamp disimpan sebagai UTC dan `period_year` tetap mengikuti tanggal Jakarta.
- Baris `(document_type_id, period_year)` dibuat memakai `insertOrIgnore`/upsert, lalu dibaca dengan `FOR UPDATE` sebelum `last_value` dinaikkan.
- Transaksi memakai maksimum tiga attempt untuk deadlock atau transient concurrency conflict yang dikenali Laravel.
- Unique constraints dari OFF-0201 tetap menjadi lapisan pertahanan terakhir untuk sequence, nomor, dan source entity.
- Retry dengan source polymorphic yang sama mengembalikan dokumen yang sudah terbit tanpa increment, dokumen, atau audit kedua. Guard ini tetap berlaku jika tipe kemudian dinonaktifkan.
- Tipe nonaktif tidak dapat menerbitkan nomor baru. Source wajib merupakan model UUID tersimpan.
- Stored pattern diparsing dan divalidasi ulang saat penerbitan; pola invalid menyebabkan seluruh increment dan insert rollback.
- Lebar `{SEQ:n}` merupakan minimum padding. Nilai di atas batas padding, seperti 10000 untuk `{SEQ:4}`, tidak wrap dan tetap diterbitkan sebagai `10000`.

## Batas fase

Service ini dapat dipanggil dari transaksi workflow quotation/contract pada fase berikutnya. Lock terhadap entity draft atau pending approval tetap menjadi tanggung jawab workflow pemanggil. Load/concurrency test request bersamaan pada PostgreSQL target adalah cakupan `OFF-0206`.

## Bukti verifikasi

```text
php artisan test --filter=DocumentNumberIssuerTest
8 passed (35 assertions)

php artisan test
44 passed (211 assertions)

vendor/bin/pint --test app database routes tests
PASS
```

Smoke test rollback-only pada PostgreSQL lokal menghasilkan `SMOKE-2026-0001`, sequence `1`, dan audit dalam transaksi yang sama. Sesudah rollback, jumlah user dan tipe smoke sama-sama `0`, sehingga tidak ada data uji yang tertinggal.

Test otomatis mencakup urutan nomor, isolasi antar-tipe, reset tahun Jakarta tanpa reset job, idempotent retry, tipe nonaktif, rollback invalid pattern setelah increment, rollback foreign-key failure, audit transactional, serta sequence yang melampaui padding.
