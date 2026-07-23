@extends('layouts.back.master')

@section('title', 'Company Profile')

@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administration</div><h1 class="page-title">Company Profile</h1></div>@can('create', App\Models\CompanyProfile::class)<div class="col-auto"><a class="btn btn-primary" href="{{ route('company-profiles.create') }}">Tambah profile</a></div>@endcan</div></div></div>
<div class="page-body"><div class="container-xl">
    <form class="card card-body mb-3" method="GET"><div class="row g-2"><div class="col-md-7"><input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari kode atau nama perusahaan"></div><div class="col-md-3"><select class="form-select" name="status"><option value="">Semua status</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option></select></div><div class="col-md-2"><button class="btn w-100">Filter</button></div></div></form>
    <div class="card"><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Kode</th><th>Perusahaan</th><th>Lokasi</th><th>Template</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($profiles as $profile)<tr><td><a href="{{ route('company-profiles.show', $profile) }}">{{ $profile->company_code }}</a></td><td><strong>{{ $profile->display_name }}</strong><div class="text-secondary">{{ $profile->legal_name }}</div></td><td>{{ $profile->city }}, {{ $profile->country }}</td><td>{{ $profile->templates_count }}</td><td><span class="badge {{ $profile->is_active ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-end">@can('update', $profile)<a class="btn btn-sm" href="{{ route('company-profiles.edit', $profile) }}">Edit</a>@endcan</td></tr>
    @empty<tr><td colspan="6" class="text-center text-secondary py-4">Belum ada Company Profile.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $profiles->links() }}</div>
</div></div>
@endsection
