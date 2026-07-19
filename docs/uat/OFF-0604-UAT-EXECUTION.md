# OFF-0604 — UAT Execution Record

## Identitas pelaksanaan

| Field | Nilai |
| --- | --- |
| Environment/domain | Belum diisi |
| Release/commit | Belum diisi |
| Tanggal mulai UTC | Belum diisi |
| Tanggal selesai UTC | Belum diisi |
| Koordinator UAT | Belum diisi |
| Scope exception | Contract — Fase 5 ditunda |

## Automated preflight

| Pemeriksaan | Status | Bukti |
| --- | --- | --- |
| Full regression lokal 2026-07-19 | PASS | 105 test, 612 assertion; 6 opt-in kemudian dijalankan terpisah |
| PostgreSQL concurrency 2026-07-19 | PASS | 5 test, 73 assertion |
| Real Chrome PDF 2026-07-19 | PASS | 1 test, 5 assertion |
| Security gate lokal | PASS | 8 pemeriksaan dasar |
| Operations check lokal | PASS | Queue tables/backlog/failed/private storage |
| Backup/restore production evidence | PENDING | OFF-0603 masih terbuka |
| Remote HTTPS/SSO preflight | PENDING | Membutuhkan URL UAT |

## Hasil skenario manusia

| ID | Penguji | Waktu UTC | Status | Evidence/nomor dokumen | Defect/catatan |
| --- | --- | --- | --- | --- | --- |
| UAT-01 |  |  | NOT RUN |  |  |
| UAT-02 |  |  | NOT RUN |  |  |
| UAT-03 |  |  | NOT RUN |  |  |
| UAT-04 |  |  | NOT RUN |  |  |
| UAT-05 |  |  | NOT RUN |  |  |
| UAT-06 |  |  | NOT RUN |  |  |
| UAT-07 |  |  | NOT RUN |  |  |
| UAT-08 |  |  | NOT RUN |  |  |
| UAT-09 |  |  | NOT RUN |  |  |
| UAT-10 |  |  | NOT RUN |  |  |
| UAT-11 |  |  | NOT RUN |  |  |
| UAT-12 |  |  | NOT RUN |  |  |
| UAT-13 |  |  | NOT RUN |  |  |
| UAT-14 |  |  | NOT RUN |  |  |
| UAT-15 |  |  | NOT RUN |  |  |

Allowed status: `PASS`, `FAIL`, `BLOCKED`, `NOT RUN`.

## Defect summary

| Defect ID | Severity | Skenario | Status | Owner | Target/re-test |
| --- | --- | --- | --- | --- | --- |
|  |  |  |  |  |  |

## Sign-off

Jangan isi sebelum exit criteria pada UAT plan terpenuhi.

| Pihak | Nama/identitas | Keputusan | Waktu UTC | Catatan |
| --- | --- | --- | --- | --- |
| Pemilik proses |  | PENDING |  |  |
| Operator TI |  | PENDING |  |  |

Keputusan yang diperbolehkan: `APPROVED`, `REJECTED`. Persetujuan bersyarat harus mencantumkan defect Medium/Low, owner, dan tenggat secara eksplisit.
