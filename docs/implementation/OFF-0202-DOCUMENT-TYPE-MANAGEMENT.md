# OFF-0202 - Document Type Management

Status: **Selesai**  
Tanggal verifikasi: **2026-07-18**

## Implementasi

- CRUD tipe dokumen tersedia pada route `/document-types` dan hanya dapat diakses sesuai permission `document-types.read`/`document-types.manage`.
- Form create/edit menggunakan Alpine segment builder untuk menyusun literal, token tanggal, dan sequence tanpa menerima expression bebas.
- Server menjadi sumber kebenaran pola: literal divalidasi, token dibatasi pada `{YYYY}`, `{YY}`, `{MM}`, `{MONTH_ROMAN}`, dan pola wajib memiliki tepat satu `{SEQ:n}` dengan lebar 1-10.
- Endpoint preview menghasilkan pola tersimpan dan contoh nomor menggunakan waktu bisnis `Asia/Jakarta`.
- Perubahan pola hanya mengubah konfigurasi tipe untuk penerbitan berikutnya dan tidak menyentuh register dokumen lama.
- Tipe dapat diaktifkan/nonaktifkan. Hard delete hanya diizinkan sebelum tipe mempunyai document atau sequence; setelah digunakan, operator diarahkan untuk menonaktifkannya.
- Create, update, activation, deactivation, dan deletion dicatat pada audit log dengan snapshot before/after yang relevan.
- Form create/edit dapat menetapkan `latest sequence` untuk tahun bisnis berjalan. Penerbitan berikutnya melanjutkan dari nilai tersebut + 1. Nilai bersifat monotonic (tidak dapat diturunkan), diubah dalam transaksi yang sama dengan konfigurasi tipe, dan dicatat sebagai audit `document_sequence.latest_value_updated`.

## Bukti verifikasi

```text
php artisan test
36 passed (176 assertions)

php artisan test --filter=DocumentTypeManagementTest
7 passed (32 assertions)

npm run build
56 modules transformed; built successfully

php artisan view:cache
Blade templates cached successfully

vendor/bin/pint --test app database routes tests
PASS
```

Feature test meliputi create/update dari segments, normalisasi code, preview token, validasi sequence dan literal berbahaya, policy read/manage, audit trail, aktivasi/nonaktivasi, larangan menghapus tipe terpakai, penghapusan tipe yang belum digunakan, serta round-trip parser pola.
