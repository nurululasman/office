# OFF-0104 - Role, permission, policy, dan bootstrap administrator

Status: **selesai** pada 18 Juli 2026.

## Implementasi

- Menambahkan tabel `roles`, `permissions`, `role_user`, dan `permission_role` dengan foreign key serta unique/primary constraint pada pivot.
- Menambahkan 32 permission sesuai catalog `OFF-0003`.
- Menambahkan delapan role bisnis: `system-admin`, `document-admin`, `document-officer`, `quotation-maker`, `quotation-approver`, `contract-maker`, `contract-approver`, dan `auditor`.
- Menambahkan role teknis `office-user` tanpa permission domain sebagai role awal seluruh user JIT.
- Role `system-admin` memperoleh seluruh permission melalui mapping database, bukan bypass hard-coded.
- Claim role dari access token SSO tidak dibaca dan tidak pernah disalin menjadi role Office.
- Pemeriksaan permission membaca relasi database pada setiap authorization check; perubahan role berlaku pada request berikutnya tanpa cache/session permission.
- Permission dapat dipakai melalui Laravel Gate, misalnya `Gate::allows('quotations.complete-direct')` atau middleware `can:quotations.complete-direct`.
- Menambahkan policy untuk `User`, `Role`, dan `Permission`. Policy user mencegah admin menonaktifkan dirinya sendiri atau mengubah role miliknya sendiri.
- Role dan permission catalog sistem dilindungi dari edit/hapus oleh policy; custom role tetap dapat ditambahkan pada fase UI administrator.
- JIT provisioning memberikan hanya role `office-user` dan tidak menghapus role bisnis user yang sudah ada saat profil disinkronkan.

Migration authorization telah dijalankan dan `DatabaseSeeder` sekarang hanya menjalankan `RolePermissionSeeder`; tidak lagi membuat akun/password contoh.

## Bootstrap system administrator

Bootstrap memakai pasangan identity SSO yang presisi, bukan email:

```dotenv
OFFICE_BOOTSTRAP_ADMIN_SSO_ISSUER=https://alamat-sso
OFFICE_BOOTSTRAP_ADMIN_SSO_SUBJECT=uuid-user-sso
```

Prosedur:

1. Jalankan migration dan catalog seeder.
2. Login satu kali dengan akun calon administrator agar shadow user JIT terbentuk.
3. Isi kedua environment variable di atas dengan issuer dan subject user tersebut.
4. Bersihkan configuration cache.
5. Jalankan bootstrap seeder secara eksplisit.

```powershell
php artisan config:clear
php artisan db:seed --class=BootstrapSystemAdminSeeder --force
```

Seeder fail-closed bila konfigurasi kosong, identity tidak ditemukan, user tidak aktif, atau role catalog belum tersedia. Seeder idempotent dan tidak membuat password lokal atau user bayangan dari data environment.

## Matriks penting

- `quotation-maker` mempunyai `quotations.complete-direct`, tetapi tidak mempunyai `quotations.approve`.
- `contract-maker` mempunyai `contracts.complete-direct`, tetapi tidak mempunyai `contracts.approve`.
- Approver memiliki approve/reject/void domain masing-masing.
- Auditor hanya mendapat akses baca bisnis dan audit.
- `office-user` tidak memiliki permission domain.
- `system-admin` mempunyai seluruh 32 permission tetapi tetap tunduk pada invariant record policy dan larangan self-approval pada mode maker-checker.

## Bukti verifikasi

```text
php artisan migrate --force
  2026_07_18_000002_create_authorization_tables ... DONE

php artisan db:seed --class=DatabaseSeeder --force
  RolePermissionSeeder ... DONE

composer lint
  Pint: passed

php artisan test
  Tests: 21 passed (104 assertions)
```

Test mencakup idempotency seeder, jumlah catalog, mapping maker/approver, role JIT tanpa akses, evaluasi permission setelah role dicabut, bootstrap administrator idempotent, dan policy anti-self-management.

Audit infrastructure ditambahkan pada `OFF-0105`. Bootstrap `system-admin` sekarang menghasilkan satu event `authorization.role.assigned` yang idempotent; audit perubahan role melalui UI/service administrator dilengkapi ketika layar pengelolaan user dibuat.
