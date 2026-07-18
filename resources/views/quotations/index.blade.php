@extends('layouts.back.master')

@section('title', 'Quotation')

@section('content')
    <div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Quotation</div><h1 class="page-title">Daftar quotation</h1></div>
        @can('create', App\Models\Quotation::class)<div class="col-auto ms-auto"><a href="{{ route('quotations.create') }}" class="btn btn-primary">Buat draft</a></div>@endcan
    </div></div></div>
    <div class="page-body"><div class="container-xl">
        <form method="GET" class="card mb-3"><div class="card-body"><div class="row g-2">
            <div class="col-md-6"><label class="form-label" for="q">Pencarian</label><input class="form-control" id="q" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Subjek atau pelanggan"></div>
            <div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">Semua status</option>@foreach(['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'rejected' => 'Rejected', 'complete' => 'Complete', 'void' => 'Void'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-auto align-self-end"><button class="btn btn-primary">Filter</button></div>
        </div></div></form>
        <div class="card"><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Tanggal</th><th>Subjek / Pelanggan</th><th>Pembuat</th><th>Status</th><th class="w-1"></th></tr></thead><tbody>
            @forelse($quotations as $quotation)<tr><td>{{ $quotation->quotation_date->format('d M Y') }}</td><td><div>{{ $quotation->subject }}</div><div class="text-secondary">{{ $quotation->customer_name }}</div></td><td>{{ $quotation->creator->name }}</td><td><span class="badge bg-{{ $quotation->status === 'draft' ? 'yellow' : ($quotation->status === 'complete' ? 'green' : 'secondary') }}-lt">{{ str($quotation->status)->replace('_', ' ')->headline() }}</span></td><td><a class="btn btn-sm" href="{{ route('quotations.show', $quotation) }}">Detail</a></td></tr>
            @empty<tr><td colspan="5" class="text-center text-secondary py-5">Belum ada quotation yang sesuai.</td></tr>@endforelse
        </tbody></table></div>@if($quotations->hasPages())<div class="card-footer">{{ $quotations->links() }}</div>@endif</div>
    </div></div>
@endsection
