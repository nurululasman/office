# OFF-0306 — Quotation workflow gate

## Status

**Selesai — Fase 3 lulus acceptance criteria.**

## Coverage reguler

Feature dan unit suite membuktikan:

- direct completion menerbitkan satu nomor, mengisi completion actor/waktu, mencatat audit bypass, dan tidak mengisi approval palsu;
- maker-checker submit belum menerbitkan nomor, approve menerbitkan nomor, dan reject tetap tanpa nomor;
- creator/submitter tidak dapat approve atau reject sendiri, termasuk ketika memiliki role `system-admin`;
- retry direct completion, approval, dan void tidak membuat nomor atau audit sukses kedua;
- stale edit serta stale workflow request ditolak melalui `lock_version` tanpa menimpa data baru;
- kegagalan pattern setelah sequence dimulai me-rollback sequence, document, status quotation, dan audit completion;
- quotation complete/void immutable dan nomor void tidak digunakan ulang;
- matriks seluruh ability quotation untuk office-user, document-admin, quotation-maker, quotation-approver, auditor, dan system-admin;
- dynamic item key tidak bergantung pada `rate_20`/`rate_40` dan snapshot template bertahan setelah template berubah.

## Gate PostgreSQL concurrency

`PostgresQuotationConcurrencyTest` menjalankan delapan proses PHP independen terhadap quotation direct yang sama. Setiap worker membuka koneksi PostgreSQL sendiri dan memanggil completion dengan versi awal yang sama.

Acceptance gate memastikan:

- semua worker mengembalikan quotation ID, document ID, dan nomor yang sama;
- hanya satu document source quotation yang tersimpan;
- sequence hanya bertambah menjadi `1`;
- hanya satu `quotation.approval_bypassed` dan satu `quotation.completed`;
- fixture bertanda `OFF0306` dibersihkan setelah test, termasuk saat assertion gagal.

Kode tipe dokumen quotation dapat dioverride melalui `OFFICE_QUOTATION_DOCUMENT_TYPE_CODE`; production default tetap `QUOTATION`. Gate menggunakan kode fixture unik agar tidak menyentuh konfigurasi bisnis.

## Menjalankan gate

```powershell
cd D:\MAMAN\YAHER\Development\office
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Test-PostgresConcurrency.ps1
```

Runner memerlukan PostgreSQL development/test yang sudah menjalankan seluruh migration. Pada verifikasi ini migration `2026_07_18_000005_create_quotation_tables` diterapkan ke database development `office` sebelum gate.

## Bukti verifikasi 18 Juli 2026

```text
PostgreSQL Integration: 5 passed (73 assertions)
Targeted quotation suite: 19 passed (194 assertions), 1 PostgreSQL gate skipped dalam mode default
Full default suite: 87 passed (500 assertions), 5 PostgreSQL gates skipped by default
Migration status: seluruh migration Ran
```

Lima skip pada suite default bersifat disengaja: empat gate concurrency register dokumen dan satu gate quotation. Semua lima gate lulus ketika runner PostgreSQL diaktifkan.
