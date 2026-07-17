# OFF-0003 - Role, Permission, dan Approval

- Status: Accepted, amended
- Tanggal: 2026-07-18
- Perubahan terakhir: 2026-07-18 - approval dapat dibypass melalui mode direct selama organisasi masih kecil

## Keputusan

Authorization Aplikasi Office dikelola lokal menggunakan role dan permission. Claim role dari SSO tidak langsung memberikan akses domain Office. SSO hanya membuktikan identitas dan membership aplikasi; administrator Office menetapkan role lokal.

Workflow maker-checker tetap dibangun untuk quotation dan kontrak, tetapi konfigurasi awal memakai **mode `direct`** selama struktur organisasi masih kecil. Pada mode ini maker berpermission dapat complete dokumennya sendiri; sistem mencatat approval bypass secara eksplisit. Ketika organisasi siap, administrator mengubah tipe ke `maker_checker` tanpa perubahan schema atau workflow code. Nomor resmi selalu baru diterbitkan dalam transaksi yang mengubah status menjadi `complete`.

## Role

| Slug | Tujuan |
|---|---|
| `system-admin` | Administrasi aplikasi, user, role, permission, serta akses operasional seluruh domain |
| `document-admin` | Konfigurasi tipe, format nomor, template, dan approval mode |
| `document-officer` | Penerbitan dan pembacaan register nomor surat umum |
| `quotation-maker` | Pembuatan dan pengajuan quotation |
| `quotation-approver` | Review, approve, atau reject quotation |
| `contract-maker` | Pembuatan dan pengajuan kontrak |
| `contract-approver` | Review, approve, atau reject kontrak |
| `auditor` | Read-only seluruh domain dan audit trail |

User dapat memiliki beberapa role. Role adalah kumpulan permission, bukan pengecualian hard-coded di controller. `system-admin` memiliki seluruh permission. Larangan self-approval berlaku pada dokumen dengan snapshot mode `maker_checker`; mode `direct` menggunakan permission dan audit bypass tersendiri.

## Permission catalog

### Administration

- `users.read`, `users.manage`
- `roles.read`, `roles.manage`
- `document-types.read`, `document-types.manage`
- `templates.read`, `templates.manage`
- `audit-logs.read`

### Document registry

- `documents.read`
- `documents.issue`
- `documents.void`

### Quotation

- `quotations.read`
- `quotations.create`
- `quotations.update-own`
- `quotations.update-any`
- `quotations.submit`
- `quotations.complete-direct`
- `quotations.approve`
- `quotations.reject`
- `quotations.void`
- `quotations.pdf.read`

### Contract

- `contracts.read`
- `contracts.create`
- `contracts.update-own`
- `contracts.update-any`
- `contracts.submit`
- `contracts.complete-direct`
- `contracts.approve`
- `contracts.reject`
- `contracts.void`
- `contracts.pdf.read`

## Matriks role-permission utama

| Kemampuan | System Admin | Document Admin | Document Officer | Q Maker | Q Approver | C Maker | C Approver | Auditor |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Kelola user/role | Ya | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |
| Kelola tipe/template | Ya | Ya | Tidak | Tidak | Tidak | Tidak | Tidak | Lihat |
| Terbitkan nomor umum | Ya | Tidak | Ya | Tidak | Tidak | Tidak | Tidak | Lihat |
| Buat/edit quotation draft sendiri | Ya | Tidak | Tidak | Ya | Tidak | Tidak | Tidak | Lihat |
| Submit quotation | Ya | Tidak | Tidak | Ya | Tidak | Tidak | Tidak | Lihat |
| Complete quotation mode direct | Ya | Tidak | Tidak | Ya | Tidak | Tidak | Tidak | Lihat |
| Approve/reject quotation orang lain | Ya | Tidak | Tidak | Tidak | Ya | Tidak | Tidak | Lihat |
| Buat/edit kontrak draft sendiri | Ya | Tidak | Tidak | Tidak | Tidak | Ya | Tidak | Lihat |
| Submit kontrak | Ya | Tidak | Tidak | Tidak | Tidak | Ya | Tidak | Lihat |
| Complete kontrak mode direct | Ya | Tidak | Tidak | Tidak | Tidak | Ya | Tidak | Lihat |
| Approve/reject kontrak orang lain | Ya | Tidak | Tidak | Tidak | Tidak | Tidak | Ya | Lihat |
| Void quotation/kontrak | Ya | Tidak | Tidak | Tidak | Approver sesuai domain | Tidak | Approver sesuai domain | Lihat |
| Audit log | Ya | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak | Ya |

Label `Ya` tetap tunduk pada record policy, status, dan invariant domain. Auditor tidak boleh mengakses secret SSO, token, atau credential aplikasi walaupun dapat melihat audit bisnis.

## Approval mode

`document_types.approval_mode` memiliki nilai:

- `direct`: maker dengan permission `*.complete-direct` dapat complete sendiri. Digunakan sebagai konfigurasi awal quotation dan contract, serta single control nomor surat umum.
- `maker_checker`: creator/maker mengajukan, lalu user berbeda dengan permission `*.approve` menyelesaikan. Workflow ini sudah tersedia dan dapat diaktifkan per tipe.

Perubahan approval mode hanya berlaku untuk draft baru. Setiap quotation/contract menyimpan `approval_mode` snapshot agar perubahan konfigurasi tidak mengubah workflow dokumen yang sedang berjalan. Perubahan mode hanya boleh dilakukan `document-admin`/`system-admin` dan wajib diaudit.

## State machine quotation dan kontrak

```text
direct:        draft ------------------------> complete -> void
                         complete-direct

maker-checker: draft -> pending_approval -> complete -> void
                   ^           |
                   |           v
                   +-------- rejected
```

Aturan transisi:

1. `draft -> complete` pada mode `direct`
   - snapshot `approval_mode` harus `direct`;
   - membutuhkan permission `*.complete-direct`;
   - seluruh field wajib harus valid;
   - completion dan penerbitan nomor berada dalam satu transaksi;
   - `approval_bypassed_by`, `approval_bypassed_at`, dan alasan sistem `approval_mode_direct` dicatat;
   - `approved_by`/`approved_at` tetap null agar audit tidak menyatakan approval yang tidak terjadi.
2. `draft -> pending_approval` pada mode `maker_checker`
   - membutuhkan permission `*.submit`;
   - seluruh field wajib harus valid;
   - `submitted_by` dan `submitted_at` dicatat;
   - belum menerbitkan nomor.
3. `pending_approval -> rejected`
   - membutuhkan permission `*.reject`;
   - checker tidak boleh creator atau submitter;
   - alasan reject wajib;
   - belum menerbitkan nomor.
4. `rejected -> draft`
   - maker melakukan revisi;
   - jejak rejection lama tetap di audit log.
5. `pending_approval -> complete`
   - membutuhkan permission `*.approve`;
   - checker tidak boleh creator atau submitter;
   - approval, penerbitan nomor, snapshot, dan status complete berada dalam satu transaksi;
   - `approved_by`, `approved_at`, `completed_by`, dan `completed_at` dicatat;
   - PDF final dijadwalkan hanya setelah commit.
6. `complete -> void`
   - membutuhkan permission `*.void`;
   - alasan wajib;
   - nomor tidak dihapus atau digunakan ulang.

Dokumen `pending_approval` tidak dapat diedit. Maker harus menunggu rejection atau checker dapat menolak dengan alasan koreksi. Tidak ada aksi menarik submission pada versi pertama agar audit workflow sederhana; bila dibutuhkan, tambahkan transisi `pending_approval -> draft` dengan permission dan audit tersendiri.

## Larangan self-approval dan bypass

Pada mode `maker_checker`, approval/rejection ditolak bila salah satu kondisi benar:

```text
actor.id == created_by
actor.id == submitted_by
```

Larangan berlaku walaupun actor memiliki `system-admin`, `*.update-any`, dan `*.approve`. Pada mode `direct`, tindakan maker bukan dicatat sebagai approval, melainkan `approval_bypassed`; actor harus memiliki `*.complete-direct`. Dengan demikian audit dapat membedakan approval dua orang dari completion satu orang.

## JIT provisioning dan role awal

- JIT user dari `OFF-0001` tidak otomatis memperoleh role bisnis.
- User baru mendapat role dasar tanpa permission domain sampai administrator menetapkan role.
- Bootstrap `system-admin` dilakukan eksplisit melalui konfigurasi/seeder aman dan diaudit.
- Perubahan role berlaku pada request berikutnya; session tidak boleh menyimpan permission tanpa mekanisme invalidasi.
- Menonaktifkan user lokal langsung menolak akses Office tanpa harus menunggu perubahan di SSO.

## Idempotency dan concurrency approval

- Approve menggunakan row lock pada quotation/contract.
- Request approve ulang untuk entity yang sudah complete mengembalikan dokumen/nomor yang sama jika actor berhak melihatnya.
- Dua approver bersamaan hanya boleh menghasilkan satu transition dan satu nomor.
- Approve yang kalah race tidak membuat audit approval sukses kedua.
- Reject dan approve bersamaan diserialisasi oleh lock; transition pertama yang commit menang, request lain menerima conflict state.

## Audit minimum

Event berikut wajib dicatat dengan actor, waktu, subject, before/after, IP, dan user agent:

- role/permission assigned atau revoked;
- draft created/updated;
- submitted;
- approved atau rejected;
- approval bypassed beserta mode, actor, dan alasan;
- completed dan nomor diterbitkan;
- void;
- akses/download PDF final bila kebijakan audit mengharuskannya.

## Acceptance criteria OFF-0003

- [x] Role dan permission catalog ditetapkan.
- [x] SSO role dipisahkan dari authorization lokal Office.
- [x] Workflow maker-checker quotation dan kontrak tetap tersedia.
- [x] Konfigurasi awal quotation dan kontrak memakai mode direct yang dapat dibypass secara eksplisit.
- [x] Direct completion memiliki permission dan audit marker tersendiri.
- [x] Nomor hanya terbit ketika direct completion atau approval menghasilkan status complete.
- [x] Nomor surat umum ditetapkan menggunakan single control.
- [x] Self-approval dilarang pada maker-checker; mode direct dicatat sebagai bypass, bukan approval.
- [x] State transition, idempotency, concurrency, dan audit minimum ditetapkan.
