# OFF-0105 - Application shell, dashboard, error, audit, dan health check

Status: **selesai** pada 18 Juli 2026.

## Application shell dan dashboard

- Layout Tabler lama yang masih berisi menu demo diganti dengan shell Aplikasi Office berbahasa Indonesia.
- Header memakai logo resmi `public/static/jblu.png`, nama aplikasi, identitas user, daftar role lokal, dan form logout `POST` ber-CSRF.
- Navigasi dan informasi dashboard mengikuti permission lokal. Link modul yang belum dibangun ditampilkan nonaktif agar tidak mengarah ke route palsu.
- Dashboard administrator menampilkan jumlah user aktif, jumlah role, job queue menunggu, dan audit terbaru.
- User JIT yang hanya mempunyai `office-user` menerima empty state yang menjelaskan bahwa akses modul belum diberikan.
- Query data operasional tidak dijalankan bila user tidak mempunyai permission yang bersangkutan.
- Footer menampilkan identitas PT Jayabaru Logistik Utama dan timezone bisnis.

## Error handling

Halaman error konsisten tersedia untuk status:

- `403` akses ditolak;
- `404` route tidak ditemukan;
- `419` CSRF/session formulir berakhir;
- `429` rate limit;
- `500` kesalahan internal tanpa detail exception;
- `503` maintenance/dependency belum siap.

Halaman error tidak menampilkan stack trace, credential, token, query database, atau internal hostname. Perilaku debug tetap dikendalikan oleh `APP_DEBUG` dan wajib `false` pada production.

## Audit authentication

Migration `2026_07_18_000003_create_audit_logs_table` menyimpan actor, action, subject polymorphic, before/after, context, IP, user-agent, dan waktu UTC.

Event awal yang sudah dicatat:

- `auth.login.succeeded`;
- `auth.login.failed` untuk callback ditolak/gagal;
- `auth.logout`;
- `auth.session.expired` termasuk user lokal nonaktif;
- `authorization.role.assigned` untuk bootstrap administrator.

Token, authorization code, code verifier, client secret, dan raw exception message tidak ditulis ke audit log.

## Health check

| Endpoint | Tujuan | Dependency | Respons |
|---|---|---|---|
| `GET /up` | Health bawaan Laravel | bootstrap framework | `200` bila framework hidup |
| `GET /health/live` | Liveness ringan | tidak memeriksa dependency eksternal | `200 {"status":"ok"}` |
| `GET /health/ready` | Readiness menerima traffic | PostgreSQL, tabel database queue, write/delete private storage | `200` sehat, `503` gagal |

Readiness hanya mengembalikan nama check dan status `ok`/`fail`; pesan exception dan detail koneksi tidak dikirim ke client. Probe storage memakai nama acak pada direktori `.health` dan langsung menghapus file setelah write berhasil.

## Bukti verifikasi

```text
php artisan migrate --force
  2026_07_18_000003_create_audit_logs_table ... DONE

php artisan view:cache
  Blade templates cached successfully

php artisan config:cache
  Configuration cached successfully

composer lint
  Pint: passed

php artisan test
  Tests: 24 passed (131 assertions)
```

Test mencakup readiness sehat/gagal tanpa exception leak, dashboard admin dan user tanpa permission, logo JBLU, custom 404, audit login sukses/gagal, logout, session expiry, dan bootstrap role audit idempotent.

## Status Fase 1

Seluruh item implementasi `OFF-0101` sampai `OFF-0105` selesai secara lokal. Dua gate environment tetap harus dilakukan sebelum Fase 1 dinyatakan siap staging:

1. daftarkan OAuth application Office dan isi seluruh `OFFICE_SSO_*` pada environment tujuan;
2. jalankan workflow CI pada repository remote dan lakukan login browser end-to-end terhadap SSO deployment tersebut.
