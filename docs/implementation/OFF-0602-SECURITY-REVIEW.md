# OFF-0602 — Security Review dan Hardening

## Status

Selesai pada 2026-07-19. Review tidak menemukan temuan kritis atau tinggi. Dua temuan sedang ditutup dalam milestone ini: rate limit pada endpoint sensitif dan header keamanan respons web. Gate konfigurasi produksi juga ditambahkan untuk mencegah deployment dengan konfigurasi yang tidak aman.

Scope Contract dikecualikan karena Fase 5 ditunda oleh pemilik proses.

## Hasil review

| Area | Kontrol dan bukti | Hasil |
| --- | --- | --- |
| OAuth/PKCE | State acak dan sekali pakai, verifier PKCE S256, batas umur state, serta validasi issuer/tenant/profile diuji oleh alur SSO. | Lulus |
| Token handling | Token berada di session server-side terenkripsi; cookie HTTP-only dan SameSite aman diwajibkan oleh security gate. Nilai secret tidak dicetak oleh gate. | Lulus |
| Policy/IDOR | Route Office memakai `auth` + `sso.session`; policy dijalankan sebelum akses metadata/file. Pengguna tanpa hak ditolak pada preview/download PDF dan akses quotation lintas pemilik. | Lulus |
| CSRF | Endpoint mutasi menggunakan metode non-GET di grup middleware `web`, sehingga dilindungi `ValidateCsrfToken`. Keberadaan stack diuji pada route approval. | Lulus |
| XSS | Output Blade menggunakan escaping. Payload `<script>` dan event-handler HTML diuji tetap ter-escape pada preview quotation. | Lulus |
| Rate limit | Login 10/menit/IP, callback 20/menit/IP, preview/PDF 30/menit/user, dan mutasi kritis 30/menit/user. Batas login diuji hingga respons 429. | Ditutup |
| Secret | `.env` tidak terlacak; pemeriksaan pola secret pada file terlacak tidak menemukan credential aplikasi. Contoh konfigurasi hanya berisi placeholder. | Lulus |
| File access | PDF disimpan pada disk `documents` private (`serve=false`), policy dan status `ready` diverifikasi sebelum download, serta nama download disanitasi. | Lulus |
| Browser hardening | Respons web memasang `nosniff`, anti-framing, referrer policy, permissions policy, CSP minimum, dan HSTS hanya pada HTTPS. | Ditutup |

## Perubahan implementasi

- `AddSecurityHeaders` dipasang pada seluruh web response.
- Named rate limiter ditambahkan untuk login, callback, preview/PDF, serta mutasi dokumen/quotation.
- Command fail-closed `office:security:check` memvalidasi konfigurasi dasar; opsi `--production` menambahkan kewajiban HTTPS, secure cookie, debug nonaktif, dan endpoint SSO HTTPS.
- `SecurityHardeningTest` mengunci kontrak header, middleware, rate limit aktual, dan perilaku security gate.
- Regression XSS ditambahkan pada preview quotation.

## Gate rilis

Jalankan setelah konfigurasi produksi tersedia dan sebelum traffic dialihkan:

```powershell
php artisan config:clear
php artisan office:security:check --production
php artisan test
```

Command hanya menampilkan nama pemeriksaan dan status PASS/FAIL; nilai konfigurasi sensitif tidak ditampilkan.

## Verifikasi 2026-07-19

```text
php artisan office:security:check
PASS — 8 pemeriksaan konfigurasi dasar

php artisan test tests\Feature\SecurityHardeningTest.php
PASS — 4 test, 36 assertion

php artisan test --compact
PASS — 102 test, 607 assertion; 6 test opt-in dilewati
```

Enam test yang dilewati adalah gate integrasi opt-in (Chrome renderer dan concurrency PostgreSQL), bukan kegagalan. Gate tersebut telah dijalankan pada OFF-0601. `Pint --test` dan `git diff --check` juga lulus.

## Risiko residual

- CSP saat ini membatasi `base-uri`, framing, dan object embedding. Migrasi ke nonce/hash untuk `script-src` dan `style-src` perlu dilakukan bersama refactor asset inline agar tidak merusak UI/PDF.
- Rate limit menggunakan limiter Laravel aktif. Produksi multi-instance wajib memakai cache bersama agar counter konsisten antar-instance.
- Gate produksi memvalidasi konfigurasi, bukan menggantikan rotasi secret, vulnerability scanning dependency, WAF, atau pengujian penetrasi independen.
