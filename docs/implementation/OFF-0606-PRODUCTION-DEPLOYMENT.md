# OFF-0606 — Production Deployment, Smoke, Observasi, dan Rollback

## Status

Release gate, read-only smoke command, deployment sequence, observation window, dan rollback runbook selesai pada 2026-07-19. OFF-0606 tetap terbuka karena OFF-0603, OFF-0604, dan OFF-0605 masih menunggu evidence eksternal; deployment production belum dilakukan.

Scope Contract dikecualikan karena Fase 5 ditunda. Release notes dan komunikasi pengguna wajib menyebut pengecualian tersebut.

## Fail-closed release gate

```powershell
php artisan office:release:check --production
```

Gate production mewajibkan:

- konfigurasi security production dan HTTPS;
- environment production, PostgreSQL, UTC, business timezone valid, serta frontend build;
- database, queue, failed-job threshold, dan private storage sehat;
- backup/WAL/private-file serta restore drill memenuhi RPO/RTO;
- UAT process owner dan operator berstatus `APPROVED`, tanpa defect Critical/High;
- manifest cutover dua pihak lolos dry-run.

Tidak ada opsi bypass. Evidence berada di path read-only yang dikonfigurasi lewat `OFFICE_BACKUP_EVIDENCE_PATH`, `OFFICE_RESTORE_EVIDENCE_PATH`, `OFFICE_UAT_EVIDENCE_PATH`, dan `OFFICE_CUTOVER_MANIFEST_PATH`.

## Deployment sequence

1. Tetapkan release ID/commit, incident commander, operator, maintenance window, dan release lama yang akan menjadi rollback target.
2. Pastikan CI, OFF-0601, security, backup/restore, UAT, dan cutover approvals lulus. Jalankan `office:release:check --production` pada konfigurasi production sebelum maintenance.
3. Ambil recovery point baru dan verifikasi WAL/private-file archive. Jangan lanjut bila evidence belum diperbarui.
4. Freeze penerbitan legacy dan jalankan delta reconciliation OFF-0605. Aktifkan maintenance mode Office:

   ```bash
   php artisan down --retry=60 --refresh=15 --secret="$MAINTENANCE_SECRET"
   ```

5. Drain queue dengan batas waktu. Hentikan worker melalui Supervisor/systemd setelah pekerjaan aktif selesai; jangan membunuh job penerbitan/PDF di tengah transaksi.
6. Deploy immutable release ke direktori baru, misalnya `/var/www/office/releases/<release-id>`. Install/build sebagai deployment user:

   ```bash
   composer install --no-dev --classmap-authoritative --no-interaction
   npm ci
   npm run build
   php artisan optimize:clear
   ```

7. Hubungkan `.env`/secret dan shared `storage` tanpa menyalinnya ke artifact. Pastikan web user hanya dapat menulis `storage` dan `bootstrap/cache`.
8. Catat `php artisan migrate:status`, lalu jalankan migration additive yang telah diuji:

   ```bash
   php artisan migrate --force
   php artisan office:initial-data:apply /secure/office-cutover.json --apply
   php artisan optimize
   ```

9. Jalankan `office:release:check --production` lagi dari release baru. Bila gagal, jangan pindahkan symlink/current release.
10. Alihkan symlink atomik ke release baru. Restart PHP-FPM, scheduler, dan dua worker (`default`, `pdf`), lalu jalankan `php artisan queue:restart`.
11. Buka traffic dengan `php artisan up`, kemudian jalankan smoke read-only:

    ```bash
    php artisan office:smoke --url=https://office.example.com
    ```

12. Pemilik proses melakukan satu login/read-only check. Penerbitan pertama diawasi operator dan direkonsiliasi terhadap nilai cutover; jangan membuat nomor uji di production.

## Smoke contract

`office:smoke` hanya mengirim tiga request GET:

- `/health/live` harus 200/status `ok`;
- `/health/ready` harus 200/status `ok`;
- `/auth/login` harus redirect HTTPS menuju SSO;
- respons harus memiliki `nosniff`, anti-frame, dan HSTS.

HTTP URL ditolak. Command tidak login, tidak membuat data, tidak menerbitkan nomor, dan tidak mengikuti redirect ke SSO.

Runner final:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\Test-Off0606ReleaseGate.ps1 `
  -BaseUrl https://office.example.com
```

## Observation window

Minimum 60 menit setelah traffic dibuka dan dilanjutkan business-day monitoring 24 jam:

- menit 0, 5, 15, 30, 60: liveness/readiness, 5xx, latency, DB connections/locks, queue default/pdf, failed jobs, disk, Chrome/PDF errors;
- verifikasi scheduler tick dan backup/WAL/private-file evidence terus diperbarui;
- rekonsiliasi nomor pertama setiap tipe dengan approved cutover value;
- verifikasi audit completion/approval/void dan hash PDF tanpa mencatat isi sensitif;
- catat release ID, timestamp, metrik, operator, dan keputusan lanjut/rollback.

Stop-the-line: nomor ganda/salah, unauthorized access, data corruption, secret exposure, migration inconsistency, readiness gagal, queue tidak pulih, backup/WAL stale, atau PDF resmi salah. Hentikan writer dan deklarasikan insiden.

## Rollback

### Code rollback — schema tetap kompatibel

1. `artisan down`, hentikan/drain worker, dan simpan log/metrics.
2. Alihkan symlink ke release lama yang sudah diketahui baik.
3. Jangan jalankan `migrate:rollback` otomatis; migration additive tetap dipertahankan.
4. Jalankan `optimize:clear`, `optimize`, restart PHP-FPM/worker/scheduler, lalu `artisan up`.
5. Jalankan release check dan smoke dari release lama; rekonsiliasi job serta nomor selama incident window.

### Data/schema recovery

- Hanya incident commander dapat menyetujui schema rollback atau PITR.
- Sebelum nomor pertama production, recovery ke pre-deploy point dapat dilakukan dengan tetap membekukan legacy/Office dan mengulang rekonsiliasi.
- Setelah nomor diterbitkan, jangan menurunkan sequence atau menghapus dokumen. Restore ke instance baru, rekonsiliasi semua nomor/audit/file, lalu putuskan corrective migration atau controlled cutover baru.
- `migrate:rollback` hanya bila `down()` diuji, non-destructive, dan change record menyebut step tepat.

## Verifikasi repository 2026-07-19

```text
ReleaseReadinessTest: 4 passed, 7 assertions
Full regression: 113 passed, 636 assertions; 6 opt-in skipped
Local release gate: PASS
Production release gate lokal: expected FAIL karena evidence OFF-0603/0604/0605 belum tersedia
```
