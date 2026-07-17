# OFF-0001 - Kontrak Integrasi SSO Office

- Status: Accepted
- Tanggal: 2026-07-17
- Sumber: checkout proyek `D:\MAMAN\YAHER\Development\sso`

## Konteks

Aplikasi Office menggunakan proyek SSO/IAM JBLU yang telah dibangun sebelumnya sebagai identity provider. Audit dilakukan terhadap route, konfigurasi, controller, service, dan feature test pada checkout SSO saat ini.

SSO saat ini mengimplementasikan OAuth 2.0 Authorization Code dengan PKCE S256, refresh-token rotation, access token JWT RS256, token revocation, dan endpoint profile internal. Walaupun scope `openid profile email` tersedia, implementasi belum menerbitkan `id_token` dan belum menyediakan discovery document, JWKS, atau endpoint `userinfo` standar. Karena itu integrasi ini disebut **OAuth 2.0 profil internal SSO JBLU**, bukan OIDC penuh.

## Kontrak endpoint

Seluruh URL dibentuk dari `OFFICE_SSO_BASE_URL`; tidak di-hardcode dalam kode aplikasi.

| Fungsi | Method dan path | Catatan |
|---|---|---|
| Authorization | `GET /oauth/authorize` | `response_type=code`, `state` wajib dari Office, PKCE `S256` wajib |
| Approval consent | `POST /oauth/authorize` | Dikelola UI SSO, bukan dipanggil Office secara langsung |
| Token exchange/refresh | `POST /oauth/token` | Client confidential; mendukung `authorization_code` dan `refresh_token` |
| Profile/token validation | `GET /api/v1/auth/me` | Bearer access token; sumber identitas dan tenant aktif |
| Token revocation | `POST /oauth/revoke` | Client authentication wajib; revoke refresh token juga mencabut access token pasangannya |

Konfigurasi lingkungan Office yang dibutuhkan:

```dotenv
OFFICE_SSO_BASE_URL=
OFFICE_SSO_CLIENT_ID=
OFFICE_SSO_CLIENT_SECRET=
OFFICE_SSO_REDIRECT_URI="${APP_URL}/auth/callback"
OFFICE_SSO_SCOPES="openid profile email"
```

`OFFICE_SSO_CLIENT_SECRET` wajib berasal dari secret manager/environment dan tidak boleh di-commit. Redirect URI harus terdaftar persis pada application record di SSO.

## Issuer dan environment

- Issuer SSO dihasilkan dari `APP_URL` milik deployment SSO (`config('sso.issuer')`).
- Checkout lokal saat audit menggunakan `http://localhost`.
- UAT yang digunakan pada deployment sebelumnya adalah `https://uat-sso.jayabaru-logisticspark.com`; nilai ini harus diverifikasi kembali dari konfigurasi deployment sebelum Office staging dihubungkan.
- URL production belum ditetapkan dan menjadi konfigurasi deployment, bukan keputusan yang di-hardcode.
- Discovery URL: **tidak tersedia** pada implementasi saat ini.

Karena discovery/JWKS tidak tersedia, Office tidak mendecode JWT untuk menetapkan identitas atau permission. Office menganggap token opaque dari sisi client dan memanggil `/api/v1/auth/me` melalui server.

## Identitas pengguna

Identity key yang stabil adalah gabungan:

```text
sso_issuer + user.id
```

Pemetaan response `/api/v1/auth/me`:

| SSO | Office | Aturan |
|---|---|---|
| `user.id` | `users.sso_subject` | Identifier utama, tidak boleh berubah |
| configured base/issuer | `users.sso_issuer` | Bagian unique identity |
| `user.email` | `users.email` | Profile mutable, bukan identifier |
| `user.username` | `users.name` | Nama tampilan sementara sampai SSO mempunyai field nama lengkap |
| `user.avatar_url` | `users.avatar_url` | Optional |
| `tenant_id` | metadata/audit login | Harus sesuai tenant application Office |
| `user.status` | gate login | Harus `active` |

Claim JWT `sub` pada SSO berisi UUID user yang sama, tetapi Office tetap mengambil identitas dari response profile terautentikasi. Role di dalam JWT tidak otomatis menjadi permission Office; mapping authorization diputuskan pada `OFF-0003`.

## Provisioning Office

Provisioning menggunakan **just-in-time shadow user** setelah callback berhasil:

1. Office menukar authorization code dari backend menggunakan code verifier dan client credential.
2. Office memanggil `/api/v1/auth/me` dengan access token.
3. Login ditolak jika profile gagal, user tidak aktif, tenant tidak cocok, atau subject kosong.
4. Office mencari user melalui unique `(sso_issuer, sso_subject)`.
5. Jika belum ada, Office membuat shadow user lokal dengan status aktif dan role minimum yang diputuskan pada `OFF-0003`.
6. Jika sudah ada, Office menyinkronkan email, nama tampilan, avatar, dan `last_login_at`; role lokal tidak ditimpa.
7. Office membuat session Laravel lokal dan menyimpan access/refresh token secara encrypted server-side.

User harus lebih dahulu tersedia, aktif, menjadi member aktif tenant yang menaungi application Office, dan memiliki akses ke OAuth application pada SSO. JIT Office tidak membuat akun atau membership baru di SSO.

## Logout dan lifecycle token

Logout Office dilakukan secara berurutan:

1. kirim refresh token ke `/oauth/revoke` menggunakan client authentication;
2. hapus access token, refresh token, dan PKCE transient state dari penyimpanan server Office;
3. invalidate serta regenerate Laravel session/CSRF token;
4. arahkan pengguna ke halaman logged-out Office.

SSO belum memiliki RP-initiated/global browser logout. Logout Office mencabut sesi aplikasi Office, tetapi tidak menjanjikan logout dari admin console atau aplikasi SSO lain. Refresh token diputar setiap refresh; Office wajib mengganti token lama secara atomic dan tidak menggunakannya kembali.

## Kontrol keamanan wajib

- `state` random, single-use, terikat session, dan memiliki expiry singkat.
- PKCE verifier disimpan sementara hanya pada server/session; challenge menggunakan S256.
- Token exchange dan profile call hanya dilakukan backend.
- Token tidak dikirim ke JavaScript, local storage, query string, atau log.
- Session cookie Office menggunakan `Secure`, `HttpOnly`, dan `SameSite=Lax` di HTTPS.
- Kegagalan callback tidak boleh membuat shadow user atau session parsial.
- Client secret dapat dirotasi tanpa perubahan kode.

## Gap yang terverifikasi dan prerequisite Fase 1

Checkout SSO saat ini melindungi `/oauth/authorize` dengan middleware Laravel `auth`, tetapi hanya memiliki halaman login browser bernama `admin.login`; belum ada login-entry umum yang secara eksplisit menyimpan intended authorization URL lalu kembali ke consent screen. Sebelum `OFF-0102` dinyatakan selesai, salah satu solusi berikut harus tersedia dan diuji di proyek SSO:

- halaman login user umum untuk OAuth authorization; atau
- mekanisme redirect login yang aman ke halaman login yang ada, dengan intended URL dan tenant context tetap terjaga.

Gap ini tidak mengubah kontrak callback Office, tetapi memblokir end-to-end login bagi browser yang belum mempunyai session SSO.

### Resolusi prerequisite

Gap login-entry diselesaikan pada `OFF-0102` tanggal 18 Juli 2026 di checkout SSO. Route browser umum `GET/POST /login` sekarang menerima user aktif tanpa mensyaratkan role administrator. Middleware guest mengarahkan request OAuth ke route tersebut, Laravel menyimpan authorization URL sebagai intended destination, dan login berhasil mengembalikan browser ke consent screen dengan parameter `state` dan PKCE tetap utuh. Login admin `/admin/login` tetap terpisah dan seluruh test regresinya lulus.

Peningkatan SSO menuju OIDC penuh (discovery, JWKS, `id_token`, `userinfo`, end-session endpoint) berada di luar `OFF-0001`. Jika kelak tersedia, migrasi protokol dibuat sebagai keputusan arsitektur terpisah.

## Bukti checkout SSO

- `routes/web.php`: endpoint authorize, token, dan revoke.
- `routes/api.php`: endpoint profile `/api/v1/auth/me` di balik validasi token.
- `config/sso.php`: issuer dari `APP_URL`, scope, TTL, dan key JWT.
- `app/Services/Auth/OAuthService.php`: PKCE, code exchange, refresh rotation, revocation, tenant membership, dan audience.
- `app/Services/Auth/TokenService.php`: claim JWT `iss`, `sub`, `aud`, `jti`, `scopes`, `tenant_id`, dan `roles`.
- `app/Http/Resources/UserResource.php`: kontrak profile user.
- `tests/Feature/OAuth/FullOAuthFlowTest.php`: alur authorize, exchange, `/me`, rotation, serta revoke.

## Acceptance criteria OFF-0001

- [x] Provider dan issuer rule teridentifikasi.
- [x] Ketersediaan discovery diverifikasi dan gap dicatat.
- [x] Subject serta pemetaan profile Office ditetapkan.
- [x] Endpoint authorization/token/profile/revoke dikonfirmasi dari kode dan test.
- [x] Aturan JIT provisioning dan prerequisite membership ditetapkan.
- [x] Logout serta lifecycle token ditetapkan.
- [x] Gap yang memblokir implementasi Fase 1 dicatat secara eksplisit.
