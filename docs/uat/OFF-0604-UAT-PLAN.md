# OFF-0604 — User Acceptance Test Plan

## Status

Paket UAT siap dijalankan. Preflight otomatis lokal lulus pada 2026-07-19. Sign-off pemilik proses belum tersedia, sehingga OFF-0604 tetap terbuka.

Contract tidak termasuk scope karena Fase 5 ditunda oleh pemilik proses. Penguji tidak boleh menandai capability Contract sebagai lulus atau gagal dalam siklus ini.

## Entry criteria

- Release artifact yang sama dengan kandidat production sudah terpasang di staging/UAT.
- Domain HTTPS, SSO client staging, database, private storage, queue worker, scheduler, Chrome, dan secret terpisah dari production.
- Migration selesai; seed kandidat untuk role, tipe dokumen, pola nomor, dan template tersedia.
- Backup staging dan restore drill OFF-0603 memiliki evidence yang dapat diverifikasi.
- Tidak ada temuan security kritis/tinggi terbuka.
- Data pengujian sintetis; tidak memakai data production tanpa anonimisasi dan approval.

Preflight operator:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\Test-Off0604UatPreflight.ps1 `
  -BaseUrl "https://uat-office.example.com" `
  -RequireBackupEvidence
```

## Peran penguji

| Peran | Penguji minimum | Fokus |
| --- | --- | --- |
| Pemilik proses | 1 | Alur bisnis, format nomor, isi dokumen, keputusan sign-off |
| Document officer/admin | 1 | Tipe dokumen, penerbitan, register, void |
| Quotation maker | 1 | Draft, preview, completion/submission |
| Quotation approver | 1 orang berbeda | Approval/rejection dan maker-checker |
| Auditor | 1 | Read-only, PDF, audit trail |
| Operator TI | 1 | Health, queue, backup evidence, log, rollback readiness |

Penguji maker dan approver wajib memakai identitas SSO berbeda.

## Skenario UAT

Catat setiap hasil pada [execution record](OFF-0604-UAT-EXECUTION.md). ID dokumen, nomor, hash/PDF, timestamp, screenshot, dan defect ID boleh dicatat; token, cookie, client secret, dan data sensitif dilarang.

| ID | Skenario | Hasil yang diterima |
| --- | --- | --- |
| UAT-01 | Login SSO pengguna aktif dan logout | Redirect/callback berhasil, profil benar, logout mengakhiri session |
| UAT-02 | Pengguna nonaktif/tanpa permission membuka halaman domain | Ditolak tanpa kebocoran data; role SSO tidak memberi permission lokal |
| UAT-03 | Admin membuat tipe dokumen, preview pola, lalu mengaktifkan | Preview sesuai pola; tipe aktif tersedia untuk penerbitan |
| UAT-04 | Officer menerbitkan dua surat pada tipe/tahun sama | Nomor unik, berurutan, register/detail/audit benar |
| UAT-05 | Officer membatalkan dokumen dengan alasan | Status VOID, nomor tetap tercatat dan tidak digunakan ulang |
| UAT-06 | Maker membuat quotation dengan item dinamis, karakter khusus, nilai kosong | Draft tersimpan; tampilan ter-escape; format tanggal/mata uang benar |
| UAT-07 | Preview quotation pendek dan panjang | Layout A4, logo, tabel multi-page, terms dan tanda tangan layak cetak |
| UAT-08 | Mode direct: maker menyelesaikan quotation dua kali | Satu nomor dan satu final PDF; bypass/audit tercatat; retry idempotent |
| UAT-09 | Mode maker-checker: maker submit, mencoba self-approve, approver lain approve | Self-approval ditolak; approval atomik menerbitkan satu nomor |
| UAT-10 | Approver menolak dengan alasan; maker merevisi dan submit ulang | History/alasan terjaga; revisi tidak merusak audit |
| UAT-11 | Ubah quotation complete dan akses PDF oleh user tanpa hak | Mutasi ditolak; download unauthorized ditolak |
| UAT-12 | Auditor mencari register dan membuka dokumen/PDF/audit | Read-only berhasil; tidak tersedia aksi mutasi |
| UAT-13 | Gagalkan satu render PDF lalu pulihkan worker/retry | Failure terlihat; retry menghasilkan file tanpa nomor/file ganda |
| UAT-14 | Health, queue, scheduler, backup/WAL/private-file evidence | Semua gate sehat dan alert uji diterima operator |
| UAT-15 | Browser desktop yang disetujui dan print-to-PDF/printer | Tidak ada clipping/overlap; filename stabil dan isi snapshot benar |

## Severity dan exit criteria

- **Critical:** akses tidak sah, nomor ganda/hilang, data corruption, secret exposure. UAT dihentikan.
- **High:** alur utama tidak dapat selesai, PDF legal tidak layak, backup/restore gate gagal. Sign-off dilarang.
- **Medium:** fungsi penting memiliki workaround terbatas. Perlu keputusan tertulis pemilik proses.
- **Low:** kosmetik/non-blocking. Boleh menjadi follow-up dengan owner dan target tanggal.

UAT lulus hanya bila seluruh skenario in-scope berstatus PASS, tidak ada Critical/High terbuka, Medium mendapat keputusan tertulis, regression setelah fix lulus, dan bagian sign-off di execution record ditandatangani pemilik proses serta operator TI.

## Re-test

Setiap defect fix harus menyebut commit/release artifact, menjalankan skenario gagal plus skenario terkait, lalu menjalankan preflight kembali. Jangan mengubah hasil lama; tambahkan baris re-test agar jejak keputusan utuh.
