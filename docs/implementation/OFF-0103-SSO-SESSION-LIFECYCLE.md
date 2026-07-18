# OFF-0103 - Provisioning dan lifecycle sesi SSO

Status: **selesai** pada 18 Juli 2026.

## Implementasi

- `GET /auth/login` membuat state dan PKCE server-side lalu mengarahkan browser ke authorization endpoint SSO.
- `GET /auth/callback` memvalidasi state single-use, menukar code, mengambil profil `/api/v1/auth/me`, dan baru kemudian membuat session lokal.
- JIT provisioning mencari user hanya dengan `(sso_issuer, sso_subject)`. Email, username/nama tampilan, avatar, dan `last_login_at` disinkronkan tanpa mengubah identity key.
- Email yang sudah dimiliki identity lain ditolak dan tidak di-auto-link untuk mencegah account takeover.
- User SSO harus berstatus `active` dan tenant-nya harus cocok. User Office yang `is_active=false` tetap ditolak walaupun SSO mengizinkan login.
- Access token dan refresh token disimpan terenkripsi di database session. Token tidak masuk URL, JavaScript, local storage, log, maupun kolom user.
- Session Laravel diregenerasi setelah login untuk mencegah session fixation.
- Middleware `sso.session` mengakhiri session ketika access token kedaluwarsa, batas maksimum sesi tercapai, data token hilang/rusak, atau user lokal dinonaktifkan.
- `POST /logout` mencabut refresh token pada SSO, menghapus token lokal, logout guard, menginvalidasi session, dan meregenerasi CSRF token.
- Kegagalan revoke SSO tidak menghalangi local logout dan hanya dicatat tanpa nilai token.
- Landing page menampilkan pesan login gagal, sesi berakhir, dan logout berhasil.

Session database development sekarang memakai `SESSION_ENCRYPT=true`. Selain enkripsi session Laravel, nilai access/refresh token juga dienkripsi secara eksplisit sebelum ditaruh pada payload session.

## Kontrak waktu sesi

- Idle timeout mengikuti `SESSION_LIFETIME` Laravel, default 120 menit.
- Absolute Office SSO session mengikuti `OFFICE_SSO_SESSION_MAX_MINUTES`, default 480 menit.
- Bila access token mempunyai expiry yang lebih cepat, session berakhir pada expiry access token tersebut.
- Versi ini tidak melakukan refresh otomatis; refresh-token rotation dapat ditambahkan kelak tanpa mengubah controller karena token lifecycle terisolasi pada service identity.

## Bukti verifikasi

```text
php artisan config:cache
  Configuration cached successfully

php artisan route:list --path=auth
  GET auth/login
  GET auth/callback

composer lint
  Pint: passed

php artisan test
  Tests: 16 passed (83 assertions)
```

Test mencakup login redirect, callback, provisioning baru, sinkronisasi profil, penolakan user lokal nonaktif, token terenkripsi, access-token expiry, guest redirect, revoke refresh token, dan local logout.

## Konfigurasi operasional

Client OAuth dan redirect URI harus sudah terdaftar pada SSO dan nilai `OFFICE_SSO_*` harus diisi pada `.env` environment yang dituju. Secret aktual tidak disimpan di repository. Tanpa konfigurasi tersebut, `/auth/login` gagal tertutup dan menampilkan pesan bahwa SSO belum tersedia.
