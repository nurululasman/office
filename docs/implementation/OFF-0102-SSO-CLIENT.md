# OFF-0102 - Client SSO Authorization Code + PKCE

Status: **selesai** pada 18 Juli 2026.

## Implementasi Office

- Menambahkan `league/oauth2-client` 2.8 sebagai implementasi adapter, tetap di belakang contract `IdentityProvider`.
- Menambahkan konfigurasi endpoint SSO dari environment: authorize, token, profile `/api/v1/auth/me`, dan revoke. Tidak ada URL maupun secret deployment yang di-hardcode.
- Membuat code verifier acak, challenge SHA-256 (`S256`), dan state acak yang single-use serta kedaluwarsa setelah 10 menit.
- Menukar authorization code dari backend memakai client credential dan code verifier.
- Memperlakukan access token sebagai opaque token lalu mengambil profil dari `/api/v1/auth/me`.
- Memvalidasi subject, email, username, status `active`, serta kecocokan `tenant_id` sebelum profil dapat dipakai Office.
- Menambahkan kolom shadow identity pada `users`: `sso_issuer`, `sso_subject`, `avatar_url`, `is_active`, dan `last_login_at`; password lokal menjadi nullable. Identity memiliki unique constraint `(sso_issuer, sso_subject)`.
- Migration `2026_07_18_000001_add_sso_identity_to_users_table` sudah dijalankan pada PostgreSQL development.

Route callback Office, JIT provisioning, token session terenkripsi, logout/revoke, dan session expiry dilanjutkan pada `OFF-0103`. Pemisahan ini menjaga `OFF-0102` fokus pada protokol, schema identitas, dan trust boundary profile.

## Perubahan prerequisite pada proyek SSO

Checkout `D:\MAMAN\YAHER\Development\sso` memperoleh login-entry browser umum:

- guest yang membuka `/oauth/authorize` diarahkan ke `/login`, bukan halaman root;
- intended authorization URL disimpan oleh session dan dipakai kembali setelah login;
- user harus aktif, tidak terkunci, dan mempunyai password valid;
- login user OAuth tidak mensyaratkan akses Admin Console;
- login admin tetap memakai route dan policy terpisah;
- login sukses tetap masuk ke audit event SSO.

## Konfigurasi deployment yang harus diisi

```dotenv
OFFICE_SSO_BASE_URL=
OFFICE_SSO_CLIENT_ID=
OFFICE_SSO_CLIENT_SECRET=
OFFICE_SSO_REDIRECT_URI="${APP_URL}/auth/callback"
OFFICE_SSO_SCOPES="openid profile email"
OFFICE_SSO_TENANT_ID=
```

Application Office dan redirect URI harus didaftarkan pada environment SSO yang dituju. Nilai aktual tidak disimpan dalam repository.

## Bukti verifikasi

Office:

```text
composer lint
  Pint: passed

php artisan test
  Tests: 10 passed (39 assertions)

composer validate --no-check-publish
  composer.json is valid
```

SSO:

```text
OAuthLoginEntryTest + AdminAuthenticationTest
  Tests: 8 passed (57 assertions)

FullOAuthFlowTest + OAuthLoginEntryTest
  Tests: 4 passed (49 assertions)

Pint (file login-entry dan test)
  passed
```
