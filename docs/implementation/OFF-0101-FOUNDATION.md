# OFF-0101 - Fondasi aplikasi

Status: **selesai** pada 18 Juli 2026.

## Implementasi

- PostgreSQL menjadi kontrak database pada `.env.example`; database development `office` berjalan melalui konfigurasi `.env` lokal.
- Sistem memakai UTC dan waktu bisnis memakai `Asia/Jakarta` melalui `config/office.php`.
- Database queue memakai queue `default`, sedangkan render PDF kelak memakai queue `pdf`. Job database dikirim setelah transaksi commit.
- Dokumen final memakai disk `documents` di `storage/app/private/documents`, tidak mempunyai URL publik, dan kegagalan penulisan dilempar sebagai exception.
- Alpine.js dimuat melalui entry point Vite dan dipakai bersama asset Tabler yang sudah tersedia. Tailwind dikeluarkan dari pipeline agar tidak mengubah style dasar Tabler.
- `composer lint` menjalankan Laravel Pint dalam mode pemeriksaan.
- workflow `.github/workflows/ci.yml` menjalankan migration dan test terhadap PostgreSQL 16, lint PHP, serta build Vite.

PostgreSQL development yang tersedia saat verifikasi adalah 15.2 dan migration fondasi lulus di sana. CI dikonfigurasi memakai PostgreSQL 16 sebagai target deployment; kompatibilitas versi 16 akan dikonfirmasi saat workflow pertama dijalankan.

## Bukti verifikasi lokal

```text
php artisan migrate --force
  0001_01_01_000000_create_users_table ... DONE
  0001_01_01_000001_create_cache_table ... DONE
  0001_01_01_000002_create_jobs_table ... DONE

composer lint
  Pint: passed

php artisan test
  Tests: 4 passed (12 assertions)

npm run build
  Vite: 56 modules transformed, build succeeded
```

CI baru dapat dinyatakan hijau setelah repository dihubungkan ke penyedia Git dan workflow benar-benar dijalankan. Konfigurasi workflow dan seluruh pemeriksaan yang sama sudah berhasil diverifikasi secara lokal.
