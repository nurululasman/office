# OFF-0006 - Timezone, Retention, Backup, RPO/RTO, dan Environment

- Status: Accepted
- Tanggal: 2026-07-18

## Ringkasan keputusan

| Area | Keputusan |
|---|---|
| System timezone | UTC untuk PHP/Laravel, PostgreSQL, queue, scheduler, dan log |
| Business timezone | `Asia/Jakarta` untuk penomoran, tanggal bisnis, dan tampilan user |
| Core document retention | Tidak ada penghapusan otomatis pada MVP |
| Production backup | Nightly PostgreSQL base backup + continuous WAL archive/PITR; private files dibackup off-host |
| Production RPO | 1 jam |
| Production RTO | 4 jam |
| Production availability target | 99.5% per bulan, tidak termasuk maintenance terjadwal yang diumumkan |
| Environment | Local, CI, staging/UAT, dan production terpisah |

Kebijakan retensi berikut adalah baseline operasional internal, bukan pernyataan pemenuhan kewajiban hukum tertentu. Legal/finance dapat memperpanjang retensi; pemendekan core record harus melalui decision baru, legal review, dan migration/pruning procedure yang diaudit.

## Timezone dan business clock

### Aturan penyimpanan

- `config/app.php` tetap menggunakan `UTC`.
- PostgreSQL session/database timezone menggunakan UTC.
- Semua timestamp memakai kolom `timestamptz` dan diserialisasi ISO-8601 UTC pada API/internal event.
- Queue delay, retry, token expiry, audit occurrence, backup timestamp, dan log menggunakan UTC.
- Host wajib sinkron waktu menggunakan NTP/chrony dan monitoring clock drift.

### Aturan bisnis

- Tambahkan `OFFICE_BUSINESS_TIMEZONE=Asia/Jakarta` dan validasi sebagai IANA timezone saat boot/release check.
- `issued_at` ditetapkan sebagai instant UTC di dalam transaksi.
- `{YYYY}`, `{YY}`, `{MM}`, `{MONTH_ROMAN}`, dan `period_year` dihitung dengan mengonversi `issued_at` ke `Asia/Jakarta`.
- Tanggal quotation/contract yang dipilih user adalah business date tersendiri dan tidak mengubah periode sequence.
- UI menampilkan waktu dalam `Asia/Jakarta` dengan label `WIB` bila jam ditampilkan.
- Scheduled maintenance/pruning didefinisikan dalam UTC bila tidak membutuhkan kalender bisnis. Job kalender bisnis menggunakan timezone eksplisit dan `withoutOverlapping`.

Contoh batas tahun:

| Instant UTC | Waktu bisnis | Period sequence |
|---|---|---|
| `2026-12-31T16:59:59Z` | `2026-12-31 23:59:59 WIB` | 2026 |
| `2026-12-31T17:00:00Z` | `2027-01-01 00:00:00 WIB` | 2027 |

Perhitungan dilakukan oleh satu service `BusinessClock`; controller tidak menghitung tahun/bulan sendiri.

Referensi: [Laravel task scheduling and timezone](https://laravel.com/docs/12.x/scheduling).

## Retention policy

### Core business records

| Data | Retensi MVP | Perlakuan |
|---|---|---|
| Document types dan pattern versions | Tidak dihapus otomatis | Nonaktifkan/versioning, jangan hard delete jika pernah digunakan |
| Document sequences dan register numbers | Tidak dihapus otomatis | Termasuk nomor void dan cutover legacy |
| Quotation, items, terms, approval/bypass history | Tidak dihapus otomatis | Draft/rejected/complete/void tetap tercatat |
| Contract, clauses/items, approval/bypass history | Tidak dihapus otomatis | Semua status tetap tercatat |
| Final PDF dan metadata/hash | Tidak dihapus otomatis | PDF dapat diregenerasi hanya dengan audit event dan tidak menimpa snapshot lama |
| Company profiles dan template versions | Tidak dihapus bila sudah direferensikan | Gunakan active/version state |
| Audit logs bisnis/authorization | Minimum 7 tahun | Setelah itu tetap dipertahankan sampai kebijakan legal menyetujui pruning |

Tidak ada cascade delete dari user ke dokumen. User dinonaktifkan; identitas actor historis tetap tersedia. Permintaan koreksi data pribadi ditangani melalui proses khusus tanpa merusak legal/audit linkage.

### Data operasional

| Data | Retensi | Mekanisme |
|---|---|---|
| Application logs production | 30 hari | Daily rotation; secret/token/content sensitif tidak boleh masuk log |
| Security/auth failure logs | 1 tahun | Structured, restricted access |
| Successful health/smoke output | 90 hari | Ringkasan, bukan response sensitif |
| Failed queue jobs | 30 hari setelah insiden selesai | Alert sebelum pruning; payload sensitif dienkripsi/minimal |
| Successful queue rows | Dihapus oleh queue driver setelah success | Audit bisnis berada di tabel domain, bukan queue |
| PDF temp/browser profile | Maksimum 24 jam | Hapus setelah success/failure; scheduled cleanup idempotent |
| OAuth transient state/PKCE | Maksimum 10 menit | Single-use; hapus setelah callback sukses/gagal |
| Local Office session | Idle 8 jam, absolute 12 jam | Logout/revocation menghapus token; expired session dipruning harian |
| Development/test fixtures | Ephemeral | Tidak boleh memakai data production nyata |

### Dokumen void

- Record dan PDF final sebelum void tetap disimpan.
- Download dokumen void hanya untuk user dengan permission read domain terkait dan auditor/admin.
- UI/PDF download selalu menampilkan status `VOID` pada metadata atau wrapper download; file lama tidak diubah diam-diam.
- Public/direct URL tidak tersedia.

## Backup architecture

### PostgreSQL

- Nightly base backup menggunakan tool PostgreSQL/managed-service equivalent.
- Continuous WAL archiving ke lokasi off-host memungkinkan point-in-time recovery.
- WAL archive harus dimonitor; kegagalan archive lebih dari 15 menit memicu alert.
- Backup encrypted in transit dan at rest, dengan credential terpisah dari application runtime.
- Backup tidak disimpan hanya pada volume/host yang sama dengan production.
- Gunakan backup manifest/checksum verification bila tool mendukung.

Retention backup:

- 14 daily recovery points;
- 8 weekly recovery points;
- 12 monthly recovery points;
- WAL dipertahankan cukup untuk seluruh recovery window base backup terkait.

PostgreSQL menjelaskan bahwa base backup yang dikombinasikan dengan rangkaian WAL memungkinkan recovery ke titik waktu tertentu. Referensi: [PostgreSQL 16 continuous archiving and PITR](https://www.postgresql.org/docs/16/continuous-archiving.html).

### Private files

- `storage/app/private` disalin encrypted ke off-host storage minimal setiap jam.
- Snapshot harian mengikuti retention 14 daily, 8 weekly, dan 12 monthly.
- Database adalah sumber kebenaran metadata file; backup file menggunakan cutoff timestamp dan manifest SHA-256.
- PDF dapat diregenerasi dari snapshot domain/template bila file hilang, tetapi regenerasi harus dicatat dan hash baru tidak menimpa record lama.
- Source asset seperti `public/static/jblu.png` juga tersedia dari versioned release artifact.

### Yang bukan backup

- Git bukan backup database atau private files.
- RAID/snapshot pada host yang sama bukan satu-satunya backup.
- Keberhasilan command backup tanpa restore test belum dianggap perlindungan yang terverifikasi.
- Secret disimpan di secret manager/password vault dengan recovery process terpisah, bukan di database dump atau repository.

## Restore dan disaster recovery

### Restore drill

- Setiap bulan: verifikasi otomatis backup age, size, manifest/checksum, dan WAL continuity.
- Setiap kuartal: restore database dan private files ke environment terisolasi; jalankan migration status, integrity query, sample PDF download, dan smoke test.
- Setahun sekali atau setelah perubahan topology besar: full disaster recovery exercise dari host kosong.
- Bukti drill mencatat backup ID, recovery target, durasi, data loss aktual, hasil smoke, dan operator; tidak menyimpan secret.

### Urutan recovery production

1. deklarasikan insiden dan hentikan writer bila masih berjalan;
2. tentukan recovery target yang disetujui incident commander/pemilik bisnis;
3. provision database/storage bersih;
4. restore base backup dan replay WAL ke target;
5. restore private file manifest yang kompatibel;
6. deploy release aplikasi yang kompatibel dan secret dari vault;
7. jalankan integrity check untuk sequence, documents, source linkage, generated file, dan audit;
8. lakukan read-only smoke test sebelum membuka traffic;
9. rekonsiliasi nomor yang mungkin diterbitkan setelah recovery point dari bukti eksternal/audit infrastructure;
10. dokumentasikan RPO/RTO aktual dan corrective action.

Nomor dokumen tidak boleh diterbitkan manual selama recovery tanpa register insiden, karena dapat menyebabkan conflict ketika sistem dipulihkan.

## RPO, RTO, dan availability

RPO dihitung sebagai maksimum data committed yang boleh hilang. RTO dihitung dari deklarasi insiden sampai layanan inti dapat digunakan kembali dengan integrity check lulus.

| Environment | RPO | RTO | Availability/SLA |
|---|---:|---:|---|
| Local development | Tidak dijamin | Rebuild maksimal 1 hari kerja | Tidak ada |
| CI | Ephemeral/rebuild | 1 jam | Tidak ada |
| Staging/UAT | 24 jam | 8 jam kerja | Best effort |
| Production | 1 jam | 4 jam | Target 99.5% per bulan |

RPO 1 jam memerlukan continuous WAL archive dan file sync setidaknya hourly. Jika monitoring menunjukkan desain deployment tidak dapat memenuhi target, release production harus fail closed atau target harus diubah melalui persetujuan tertulis sebelum go-live.

## Environment boundaries

### Local development

- PostgreSQL 16+ lokal/container, bukan SQLite untuk integration flow.
- Fake/local SSO client diperbolehkan; tidak ada production client secret.
- Private local storage dan database queue worker lokal.
- Fake mail/notification dan sample customer sintetis.
- `APP_DEBUG=true` hanya local.

### CI

- PostgreSQL service ephemeral dengan migration dari nol.
- Unit/feature test memakai fake OAuth, queue, filesystem, dan PDF renderer bila fokus test bukan integrasi.
- Job integration menjalankan concurrency test pada PostgreSQL.
- Chrome smoke PDF dijalankan pada release gate atau job terjadwal agar artifact visual tidak memperlambat seluruh test cepat.
- Secret CI terbatas pada environment tersebut dan dirotasi.

### Staging/UAT

- Topology dan versi PostgreSQL/Chrome/PHP menyerupai production.
- Database, private storage, queue, encryption key, domain, dan SSO client terpisah.
- Redirect URI staging tidak boleh terdaftar pada production client.
- Tidak menggunakan dump production kecuali telah dianonimkan dan disetujui.
- Email/outbound integration memakai sandbox/allowlist.
- Backup harian dan restore drill sebelum UAT akhir.

### Production

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS wajib.
- SSO client, encryption key, database credential, dan backup credential khusus production.
- Database tidak dapat diakses publik; least-privilege role terpisah untuk app dan backup.
- Queue worker, scheduler, Chrome health check, disk usage, backup/WAL age, error rate, dan certificate expiry dimonitor.
- Deployment artifact immutable; perubahan `.env`/secret diikuti config cache rebuild dan worker restart.
- Akses operator dan seluruh restore/retention action diaudit.

Promosi dilakukan dari release artifact yang sama: CI -> staging/UAT -> production. Database dan file tidak dipromosikan dari staging ke production.

## Scheduler dan pruning

Laravel scheduler dijalankan setiap menit oleh satu cron/systemd entry. Scheduled cleanup menggunakan `withoutOverlapping`; pada topology multi-node gunakan `onOneServer` dengan central database cache. Scheduler hanya menghapus data operasional yang sudah tercakup policy ini, tidak pernah core business record.

Rencana command pada fase implementasi:

- `office:temp-files:prune` - harian;
- `queue:prune-failed --hours=720` - harian setelah alert handling;
- `session:prune`/mekanisme Laravel equivalent - harian;
- `office:retention:report` - bulanan, dry-run/report-only untuk core data;
- `office:backup:check` - read-only check age/WAL/file manifest, minimal tiap 15 menit melalui monitoring.

Laravel mendukung timezone eksplisit, `withoutOverlapping`, dan `onOneServer` pada scheduler. Referensi: [Laravel task scheduling](https://laravel.com/docs/12.x/scheduling).

## Release gates terkait OFF-0006

Production release harus gagal bila:

- `OFFICE_BUSINESS_TIMEZONE` invalid;
- database bukan PostgreSQL atau timezone database bukan UTC;
- storage private tidak writable/readable oleh worker;
- backup/WAL archive belum pernah sukses atau terlalu lama;
- belum ada restore drill staging yang lulus;
- queue/scheduler worker tidak sehat;
- free disk di bawah threshold operasional;
- staging dan production memakai client ID, database, bucket/path, atau encryption key yang sama.

## Acceptance criteria OFF-0006

- [x] UTC system time dan `Asia/Jakarta` business time ditetapkan beserta boundary test.
- [x] Retention core business, audit, log, queue, temp, OAuth state, dan session ditetapkan.
- [x] Akses dokumen void ditetapkan.
- [x] PostgreSQL base backup, WAL/PITR, private file backup, encryption, dan retention ditetapkan.
- [x] Restore drill serta urutan disaster recovery ditetapkan.
- [x] RPO/RTO dan availability target per environment ditetapkan.
- [x] Local, CI, staging/UAT, dan production dipisahkan.
- [x] Scheduler/pruning dan release gates ditetapkan.
