# OFF-0603 — Backup, PITR, Restore, Monitoring, dan Queue Operations

## Status dan batas verifikasi

Implementasi aplikasi dan runbook selesai pada 2026-07-19. Gate otomatis, format bukti, scheduler, monitoring dependency, dan prosedur operator telah diuji lokal. Restore produksi tidak diklaim selesai: operator infrastruktur wajib menjalankan drill terisolasi dan menerbitkan bukti nyata sebelum `office:backup:check --production` dapat lulus.

Scope Contract tetap dikecualikan karena Fase 5 ditunda.

## Arsitektur produksi wajib

- PostgreSQL 16+: base backup nightly menggunakan `pg_basebackup`/managed-service equivalent dengan manifest dan checksum verification.
- WAL: `archive_mode=on`, `wal_level=replica`, dan `archive_command` idempotent menuju storage terenkripsi off-host. Alert jika archive terakhir lebih tua dari 60 menit; warning operasional sudah dimulai pada 15 menit.
- Private files: `storage/app/private` disalin terenkripsi off-host minimal setiap jam. Manifest berisi path, size, dan SHA-256; backup job memverifikasi kembali manifest setelah upload.
- Retention: 14 daily, 8 weekly, 12 monthly; WAL dipertahankan selama recovery window base backup terkait.
- Credential backup terpisah dari credential aplikasi dan tidak ditaruh di repository/manifest.

Contoh konfigurasi PostgreSQL (nilai command/path disesuaikan provider):

```conf
wal_level = replica
archive_mode = on
archive_timeout = '15min'
archive_command = 'backup-agent put-wal --if-absent %p %f'
```

Base backup harus dijalankan oleh backup agent, bukan user aplikasi:

```bash
pg_basebackup --host "$PGHOST" --username "$BACKUP_USER" --pgdata "$BASE_DIR" --format=plain --wal-method=none --checkpoint=fast --progress --manifest-checksums=SHA256
pg_verifybackup "$BASE_DIR"
```

Setelah base backup, WAL upload, dan private-file sync sukses, agent menulis atomik `backup-status.json` mengikuti [contoh manifest](../operations/backup-status.example.json). Path final diatur melalui `OFFICE_BACKUP_EVIDENCE_PATH` dan harus readable oleh aplikasi, tetapi tidak writable oleh web process.

## Restore drill/PITR

Jalankan kuartalan pada database dan storage baru yang terisolasi:

1. Catat incident/drill ID, backup ID, target UTC, operator, dan waktu mulai.
2. Restore base backup terverifikasi ke PostgreSQL bersih.
3. Konfigurasikan `restore_command` dari archive off-host dan `recovery_target_time` ke target yang disetujui; mulai PostgreSQL sampai recovery selesai.
4. Restore private files dari snapshot dengan cutoff kompatibel, lalu verifikasi seluruh SHA-256 manifest.
5. Deploy release aplikasi yang kompatibel, gunakan secret khusus drill, lalu jalankan `php artisan migrate:status`.
6. Jalankan integrity query: uniqueness `document_sequences`, `documents.number`, source linkage, generated-file metadata/hash, dan audit linkage.
7. Jalankan `php artisan office:operations:check`, regression read-only, serta download satu sample PDF.
8. Ukur RPO/RTO aktual. RPO wajib maksimal 60 menit dan RTO maksimal 240 menit.
9. Hapus environment drill sesuai change record setelah bukti dan corrective action disimpan.
10. Terbitkan atomik `restore-drill.json` mengikuti [contoh bukti](../operations/restore-drill.example.json).

Production release menjalankan:

```powershell
php artisan office:backup:check --production
```

Missing, invalid, stale, tidak off-host, tidak encrypted, checksum gagal, RPO/RTO melampaui target, atau drill lebih tua dari 93 hari menghasilkan exit code nonzero.

## Migration rollback plan

1. Sebelum deploy: backup/PITR gate harus lulus; catat migration pending dengan `php artisan migrate:status`.
2. Migration additive: deploy kode backward-compatible, jalankan `php artisan migrate --force`, verifikasi, kemudian aktifkan traffic/worker.
3. Jika kode gagal tetapi schema kompatibel, rollback release code terlebih dahulu; jangan otomatis rollback data/schema.
4. `php artisan migrate:rollback --step=N --force` hanya digunakan bila `down()` telah diuji di staging, tidak menghapus/merusak data, dan incident commander menyetujui.
5. Migration destruktif memakai expand-contract pada release terpisah setelah retention window. Pemulihan data menggunakan PITR ke instance baru lalu rekonsiliasi, bukan menimpa production tanpa approval.
6. Setelah rollback, restart worker agar tidak menjalankan payload dari release yang tidak kompatibel dan jalankan health/integrity checks.

## Monitoring dan alert

Monitoring mengeksekusi command berikut dan mengirim alert untuk exit code nonzero:

```powershell
php artisan office:backup:check
php artisan office:operations:check
php artisan schedule:list
php artisan queue:failed
```

Alert minimum: backup/WAL/private-file age, restore-drill age, scheduler miss, queue pending, failed jobs, worker heartbeat, `/health/ready`, HTTP 5xx/error rate, Chrome/PDF failures, disk free, PostgreSQL connections/replication, clock drift, TLS expiry, dan uptime bulanan 99.5%. Alert kritis mem-page on-call; warning membuat ticket. Output command tidak memuat credential atau isi dokumen.

## Queue operations

Worker production memisahkan queue normal dan PDF, contohnya:

```bash
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=90 --max-time=3600
php artisan queue:work database --queue=pdf --sleep=2 --tries=3 --timeout=180 --max-time=3600
```

- Gunakan Supervisor/systemd, autorestart, stop wait lebih panjang daripada timeout, dan jalankan `php artisan queue:restart` setelah deploy/config change.
- Inspeksi: `queue:monitor default,pdf:100`, `queue:failed`, dan log structured berdasarkan job UUID tanpa payload sensitif.
- Retry hanya setelah akar masalah ditutup: `queue:retry <uuid>`. Jangan gunakan `queue:retry all` ketika dependency masih gagal.
- Setelah insiden selesai dan bukti disimpan, failed jobs dipruning setelah 720 jam. Scheduler menjalankannya harian.
- Job PDF idempotent; retry tidak menerbitkan nomor baru dan tidak boleh menimpa snapshot/file final yang valid.

## Scheduler

Satu cron/systemd timer menjalankan `php artisan schedule:run` tiap menit. Aplikasi menjadwalkan:

- `office:operations:check` setiap menit;
- `office:backup:check` setiap 15 menit;
- `queue:prune-failed --hours=720` setiap hari pukul 02:00 UTC.

Semua memakai `withoutOverlapping` dan `onOneServer`; production harus menggunakan central cache yang mendukung lock.

## Verifikasi repository

```powershell
powershell -ExecutionPolicy Bypass -File scripts\Test-Off0603OperationsGate.ps1
php artisan test
```

Gate lokal dapat gagal sampai backup agent menerbitkan manifest nyata. Ini perilaku fail-closed yang disengaja, bukan alasan membuat bukti fiktif. Checklist OFF-0603 baru boleh dianggap operasional production setelah drill nyata menghasilkan manifest yang lulus opsi `--production`.

Hasil 2026-07-19:

```text
BackupOperationsTest: 3 passed, 5 assertions
Full regression: 105 passed, 612 assertions, 6 opt-in skipped
office:operations:check: PASS (queue tables, backlog, failed jobs, private storage)
schedule:list: PASS (operations tiap menit, backup tiap 15 menit, prune harian)
office:backup:check: FAIL (manifest off-host belum diterbitkan pada environment lokal)
```
