# OFF-0605 — Initial Data dan Cutover Sequence

## Status

Command, manifest contract, dry-run, dan regression gate selesai pada 2026-07-19. OFF-0605 tetap terbuka karena register legacy terbaru, identitas legal perusahaan, nilai sequence production, dan persetujuan dua pihak belum diberikan.

Contract tidak dimasukkan karena Fase 5 ditunda. Tipe/template Contract tidak boleh dibuat hanya dari asumsi.

## Data yang dikelola

- Seluruh role dan permission sistem dari `RolePermissionSeeder`.
- Company profile JBLU yang telah diverifikasi.
- Tipe `QUOTATION` dengan pola yang telah diterima: `QT-JBLU-{YYYY}{MM}{SEQ:4}`.
- Approval mode awal `direct`, sesuai OFF-0003; perubahan ke `maker_checker` dilakukan setelah organisasi menyetujuinya.
- Template quotation versi 1 dan schema kolom dinamis.
- `document_sequences.last_value` per tipe dan tahun berdasarkan rekonsiliasi register legacy.

## Manifest dan kontrol

Salin [initial-data-cutover.example.json](../operations/initial-data-cutover.example.json) ke lokasi terkontrol di luar repository, lalu ganti seluruh placeholder. Manifest wajib mencatat:

- referensi export register beserta SHA-256 atau controlled record ID;
- identitas pemilik proses dan administrator yang berbeda;
- identitas legal/alamat perusahaan yang telah diverifikasi;
- tipe, pola, approval mode, dan template;
- sequence terakhir per tipe/tahun, termasuk nomor void.

Command menolak JSON invalid, placeholder, approver yang sama, pola nomor invalid, sequence yang merujuk tipe asing, penurunan sequence, atau perubahan pola tipe yang sudah memiliki dokumen.

## Prosedur cutover

1. Export register legacy terbaru per tipe/tahun dan seluruh nomor void.
2. Rekonsiliasi nomor terbesar dengan ledger/dokumen fisik; jangan memakai `MAX(number)` mentah.
3. Pemilik proses memverifikasi kelengkapan dan nilai nomor terakhir.
4. Administrator memverifikasi tipe, tahun, pola, template, dan hash sumber secara independen.
5. Isi manifest; kedua identitas approval harus berbeda.
6. Jalankan dry-run:

   ```powershell
   php artisan office:initial-data:apply D:\secure\office-cutover.json
   ```

7. Buat backup/PITR recovery point dan catat change/incident ID.
8. Aktifkan freeze penerbitan pada sistem legacy dan export delta terakhir.
9. Jika ada delta, ulangi rekonsiliasi, approval, dan dry-run dengan manifest baru.
10. Terapkan sekali pada production maintenance window:

    ```powershell
    php artisan office:initial-data:apply D:\secure\office-cutover.json --apply
    ```

11. Jalankan command yang sama kembali tanpa `--apply`; dry-run harus tetap lulus sebagai bukti idempotency.
12. Preview nomor berikutnya untuk setiap tipe/tahun dan cocokkan dua pihak. Jangan menerbitkan nomor uji pada production.
13. Buka Office untuk penerbitan hanya setelah legacy tetap freeze dan checklist dua pihak ditandatangani.

Runner operator:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\Test-Off0605CutoverGate.ps1 `
  -Manifest D:\secure\office-cutover.json
```

Tambahkan `-Apply` hanya pada approved maintenance window.

## Rollback

- Sebelum penerbitan Office pertama: jika validasi pasca-apply gagal, tetap freeze kedua sistem, pulihkan database ke recovery point, perbaiki manifest, dan ulangi approval.
- Setelah nomor Office pertama terbit: jangan menurunkan sequence atau menghapus record. Deklarasikan insiden, pertahankan freeze, rekonsiliasi register Office dan legacy, lalu gunakan corrective migration/procedure yang diaudit.
- Rollback code tidak boleh menghidupkan sistem legacy untuk penerbitan tanpa keputusan incident commander dan pemilik proses.

## Verification 2026-07-19

```text
InitialDataCutoverTest: 4 passed, 17 assertions
- dry-run tidak mengubah data
- apply idempotent dan menyemai role/type/profile/template/sequence
- same approver dan placeholder ditolak
- sequence tidak dapat diturunkan
```

Nilai `118` hanya dipakai fixture regression untuk membuktikan aturan monotonic dan tidak merupakan rekomendasi sequence production.
