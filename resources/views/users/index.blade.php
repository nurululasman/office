@extends('layouts.back.master')
@section('title', 'User')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">User</h1></div></div></div></div>
<div class="page-body"><div class="container-xl"><div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
<thead><tr><th>User</th><th>Role</th><th>Login terakhir</th><th>Status</th><th class="w-1"></th></tr></thead><tbody>
@forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><div class="text-secondary">{{ $user->email }}</div></td><td>@foreach($user->roles as $role)<span class="badge bg-blue-lt me-1">{{ $role->name }}</span>@endforeach</td><td>{{ $user->last_login_at?->timezone(config('office.business_timezone'))->format('d M Y H:i') ?? '-' }}</td><td><span class="badge {{ $user->is_active ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td><a class="btn btn-sm" href="{{ route('users.edit', $user) }}">Atur akses</a></td></tr>
@empty<tr><td colspan="5" class="text-center text-secondary py-5">Belum ada user SSO.</td></tr>@endforelse
</tbody></table></div>@if($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif</div></div></div>
@endsection
