@extends('layouts.back.master')
@section('title', 'Role')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">Role</h1></div>@can('create', App\Models\Role::class)<div class="col-auto"><a class="btn btn-primary" href="{{ route('roles.create') }}">Tambah role</a></div>@endcan</div></div></div>
<div class="page-body"><div class="container-xl"><div class="card"><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Role</th><th>Permission</th><th>User</th><th>Tipe</th><th class="w-1"></th></tr></thead><tbody>
@foreach($roles as $role)<tr><td><strong>{{ $role->name }}</strong><div class="text-secondary"><code>{{ $role->slug }}</code></div></td><td>{{ $role->permissions_count }}</td><td>{{ $role->users_count }}</td><td><span class="badge {{ $role->is_system ? 'bg-azure-lt' : 'bg-secondary-lt' }}">{{ $role->is_system ? 'Sistem' : 'Kustom' }}</span></td><td><a class="btn btn-sm" href="{{ route('roles.edit', $role) }}">{{ $role->is_system ? 'Lihat' : 'Ubah' }}</a></td></tr>@endforeach
</tbody></table></div>@if($roles->hasPages())<div class="card-footer">{{ $roles->links() }}</div>@endif</div></div></div>
@endsection
