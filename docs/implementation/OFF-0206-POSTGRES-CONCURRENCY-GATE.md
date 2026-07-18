# OFF-0206 - PostgreSQL Document Numbering Concurrency Gate

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- PHPUnit memiliki suite `Integration` terpisah dengan `PostgresDocumentConcurrencyTest`.
- Worker `tests/Support/issue_document_worker.php` mem-bootstrap aplikasi dalam proses PHP independen sehingga setiap request memakai koneksi database sendiri dan row lock PostgreSQL benar-benar diuji.
- Gate concurrency bersifat opt-in melalui `OFFICE_RUN_PG_CONCURRENCY_TESTS=true`. Suite reguler berbasis SQLite menampilkan test ini sebagai skipped dan tidak mengklaim bukti locking palsu.
- Runner `scripts/Test-PostgresConcurrency.ps1` membaca hanya konfigurasi database dari `.env`, memastikan driver `pgsql`, membersihkan config cache, lalu menjalankan suite Integration.
- Setiap test membuat fixture dengan UUID/code unik dan menghapus audit, document, sequence, type, dan user fixture secara terarah pada `tearDown`, termasuk ketika assertion gagal.

## Skenario PostgreSQL

1. Dua belas proses bersamaan untuk satu tipe menghasilkan sequence kontigu `1..12`, dua belas nomor unik, dan `last_value=12`.
2. Enam request untuk tipe A dan enam request untuk tipe B dijalankan bersamaan; masing-masing tipe menghasilkan sequence independen `1..6`.
3. Sepuluh retry bersamaan memakai source UUID yang sama dan bergantian antara dua tipe; seluruh worker menerima document ID/number yang sama, hanya satu dokumen dan satu audit diterbitkan, serta total increment tetap satu.
4. Pergantian `2026-12-31 23:59:59` ke `2027-01-01 00:00:00` waktu Jakarta menghasilkan sequence pertama tahun baru bernilai satu.
5. Void mempertahankan nomor lama dan penerbitan berikutnya memakai sequence dua tanpa menggunakan ulang nomor.
6. Invalid stored pattern gagal setelah proses sequence dimulai, tetapi transaksi rollback tanpa meninggalkan row sequence atau document.

## Menjalankan gate

```powershell
cd D:\MAMAN\YAHER\Development\office
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Test-PostgresConcurrency.ps1
```

Runner menggunakan database PostgreSQL yang dikonfigurasi pada `.env`. Gunakan hanya database development/test yang diizinkan. Fixture ditandai dengan nama `OFF0206 ...` dan dibersihkan otomatis.

## Bukti verifikasi

```text
scripts/Test-PostgresConcurrency.ps1
4 passed (57 assertions)

php artisan test
55 passed (284 assertions), 4 PostgreSQL integration tests skipped by default

vendor/bin/pint --test app database routes tests
PASS
```

Pemeriksaan setelah gate menunjukkan `0` tipe dan `0` user fixture OFF0206 tersisa di PostgreSQL lokal.
