@extends('layouts.back.master')

@section('title', 'Template Quotation')

@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><div class="page-pretitle">Administrasi</div><h1 class="page-title">Template Quotation</h1></div>
    @can('create', App\Models\DocumentTemplate::class)<div class="col-auto ms-auto"><a class="btn btn-primary" href="{{ route('quotation-templates.create') }}">Buat template</a></div>@endcan
</div></div></div>
<div class="page-body"><div class="container-xl">
    <form class="card card-body mb-3" method="GET"><div class="row g-2">
        <div class="col-md-6"><input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau key"></div>
        <div class="col-md-3"><select class="form-select" name="status"><option value="">Semua status</option>@foreach(['draft' => 'Draft', 'active' => 'Aktif', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><button class="btn w-100">Filter</button></div>
    </div></form>
    <div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
        <thead><tr><th>Template</th><th>Versi</th><th>Perusahaan</th><th>Status</th><th>Diubah</th><th class="w-1"></th></tr></thead>
        <tbody>@forelse($templates as $template)<tr>
            <td><a href="{{ route('quotation-templates.show', $template) }}">{{ $template->name }}</a><div class="text-secondary"><code>{{ $template->template_key }}</code></div></td>
            <td>v{{ $template->version }}</td><td>{{ $template->companyProfile->display_name }}</td>
            <td><span class="badge {{ $template->status === 'active' ? 'bg-success-lt' : ($template->status === 'draft' ? 'bg-warning-lt' : 'bg-secondary-lt') }}">{{ ucfirst($template->status) }}</span></td>
            <td>{{ $template->updated_at?->timezone(config('office.business_timezone'))->format('d M Y H:i') }}</td>
            <td><a class="btn btn-sm" href="{{ route('quotation-templates.show', $template) }}">Detail</a></td>
        </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">Belum ada template quotation.</td></tr>@endforelse</tbody>
    </table></div>@if($templates->hasPages())<div class="card-footer">{{ $templates->links() }}</div>@endif</div>
</div></div>
@endsection
