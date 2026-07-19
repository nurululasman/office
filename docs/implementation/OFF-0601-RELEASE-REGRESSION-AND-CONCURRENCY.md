# OFF-0601 - Release regression and concurrency gate

## Scope

Gate mencakup seluruh capability yang telah diimplementasikan sampai Fase 4:

- SSO adapter/session lifecycle dan local authorization;
- document type, penerbitan nomor, register, void, dan audit;
- quotation draft, direct/maker-checker workflow, immutable/void, serta PDF;
- private storage, database queue, preview/download policy, dan metadata file;
- PostgreSQL locking, idempotency, rollback, rollover tahun, serta parallel completion;
- Chrome PDF rendering nyata.

Domain Contract tidak dianggap lulus. Fase 5 ditunda secara eksplisit oleh pemilik proses dan OFF-0501 tetap blocked sampai contoh kontrak resmi diterima. External SSO dan sign-off pengguna pada deployment remote termasuk UAT OFF-0604, bukan gate otomatis ini.

## Environment parity

Gate dijalankan pada checkout Windows dengan:

- PHP/Laravel yang sama dengan aplikasi;
- PostgreSQL 15 pada koneksi development port 5433;
- migration production sampai `2026_07_18_000006`;
- database queue dengan queue khusus `pdf` dan `after_commit`;
- private local filesystem;
- Chrome headless renderer nyata;
- timezone bisnis `Asia/Jakarta` dan timestamp persistence UTC.

Ini adalah environment production-like lokal untuk regression/load gate. Deployment, data staging, SSO live, dan UAT manusia tetap dipisahkan ke milestone berikutnya.

## Runner tunggal

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Test-Off0601ReleaseGate.ps1
```

Runner fail-fast menjalankan:

1. seluruh unit/feature/default integration suite;
2. PostgreSQL concurrency suite melalui `Test-PostgresConcurrency.ps1`;
3. opt-in real Chrome PDF gate.

Parameter `-EnvironmentFile` dapat menunjuk file environment production-like lain. `-SkipChrome` hanya untuk diagnosis lokal dan tidak memenuhi release gate lengkap.

## Load dan concurrency coverage

PostgreSQL gate menjalankan 42 proses issuer/completion paralel:

- 12 request pada satu document type: sequence harus unik dan contiguous 1-12;
- 12 request pada dua type: masing-masing sequence independen 1-6;
- 10 retry lintas dua type untuk source sama: tepat satu document/number/audit;
- 8 completion quotation direct serentak: tepat satu document, number, sequence increment, bypass audit, dan completion audit.

Gate juga menguji rollover Jakarta 2026/2027, rollback pattern invalid, serta void yang tidak memakai ulang nomor.

Fixture memakai UUID unik dan dibersihkan setelah test. Cleanup mencakup audit, queued PDF job fixture, generated file, quotation, document, sequence, template, company profile, document type, dan user.

## Temuan yang ditutup

### Migration drift

Run pertama menemukan kolom `generated_files.status` belum ada pada PostgreSQL development. `php artisan migrate:status` mengonfirmasi migration `000006` pending. Migration diterapkan dengan `php artisan migrate --force`, lalu schema gate lulus.

### Fixture cleanup drift

Setelah schema diperbarui, assertion concurrency lulus tetapi teardown gagal karena `generated_files.template_id` berelasi restrict dengan template. Cleanup test diperbaiki untuk menghapus queued job dan generated file sebelum template. Rerun sesudah perbaikan lulus dan tidak meninggalkan fixture baru.

## Hasil 18 Juli 2026

Runner terpadu:

- regression: **97 passed, 565 assertions, 6 skipped** dalam 11,05 detik;
- PostgreSQL concurrency/load: **5 passed, 73 assertions** dalam 8,83 detik;
- real Chrome PDF: **1 passed, 5 assertions** dalam 2,95 detik;
- total runner: **passed** dalam 28,46 detik.

Enam skip pada suite default adalah lima gate PostgreSQL dan satu gate Chrome yang kemudian semuanya lulus pada tahap opt-in runner yang sama.

Final rerun PostgreSQL setelah hardening cleanup: **5 passed, 73 assertions**.

## Acceptance

- [x] Regression otomatis seluruh scope Fase 0-4 lulus.
- [x] Production-engine concurrency/load gate lulus.
- [x] Real PDF renderer gate lulus.
- [x] Migration drift ditemukan dan ditutup.
- [x] Fixture tidak meninggalkan data domain/job baru setelah successful run.
- [x] Runner dapat dijalankan ulang operator dengan satu command.
- [x] Scope Contract yang ditunda dinyatakan eksplisit dan tidak disalahrepresentasikan sebagai tested.
