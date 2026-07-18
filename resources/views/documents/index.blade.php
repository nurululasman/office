@extends('layouts.back.master')

@section('title', 'Register Dokumen')

@section('content')
    <div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Dokumen</div><h1 class="page-title">Register Nomor</h1></div>
        @can('create', App\Models\Document::class)<div class="col-auto ms-auto"><a href="{{ route('documents.create') }}" class="btn btn-primary">Terbitkan nomor</a></div>@endcan
    </div></div></div>
    <div class="page-body"><div class="container-xl">
        <form method="GET" action="{{ route('documents.index') }}" class="card mb-3"><div class="card-body"><div class="row g-2">
            <div class="col-lg-4"><label class="form-label" for="q">Pencarian</label><input id="q" name="q" class="form-control" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Nomor, judul, atau peruntukan"></div>
            <div class="col-md-4 col-lg-3"><label class="form-label" for="document_type_id">Tipe</label><select id="document_type_id" name="document_type_id" class="form-select"><option value="">Semua tipe</option>@foreach($documentTypes as $type)<option value="{{ $type->getKey() }}" @selected(($filters['document_type_id'] ?? '') === $type->getKey())>{{ $type->name }} ({{ $type->code }})</option>@endforeach</select></div>
            <div class="col-md-3 col-lg-2"><label class="form-label" for="period_year">Tahun</label><select id="period_year" name="period_year" class="form-select"><option value="">Semua tahun</option>@foreach($periodYears as $year)<option value="{{ $year }}" @selected((string) ($filters['period_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="col-md-3 col-lg-2"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="">Semua status</option><option value="issued" @selected(($filters['status'] ?? '') === 'issued')>Terbit</option><option value="void" @selected(($filters['status'] ?? '') === 'void')>Void</option></select></div>
            <div class="col-auto align-self-end"><button class="btn btn-primary" type="submit">Filter</button></div>
            @if(array_filter($filters, fn ($value) => $value !== null && $value !== ''))<div class="col-auto align-self-end"><a href="{{ route('documents.index') }}" class="btn">Reset</a></div>@endif
        </div></div></form>
        <div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
            <thead><tr><th>Nomor</th><th>Tipe</th><th>Judul / Peruntukan</th><th>Terbit</th><th>Status</th><th class="w-1"></th></tr></thead>
            <tbody>@forelse($documents as $document)<tr>
                <td><code>{{ $document->number }}</code><div class="text-secondary small">Periode {{ $document->period_year }}</div></td>
                <td>{{ $document->documentType->name }}</td>
                <td><div>{{ $document->title }}</div><div class="text-secondary text-truncate" style="max-width: 28rem">{{ $document->purpose }}</div></td>
                <td>{{ $document->issued_at->timezone(config('office.business_timezone'))->format('d M Y H:i') }}<div class="text-secondary small">{{ $document->issuer->name }}</div></td>
                <td>@if($document->voided_at)<span class="badge bg-danger-lt">Void</span>@else<span class="badge bg-success-lt">Terbit</span>@endif</td>
                <td><a href="{{ route('documents.show', $document) }}" class="btn btn-sm">Detail</a></td>
            </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">Tidak ada dokumen yang sesuai filter.</td></tr>@endforelse</tbody>
        </table></div>@if($documents->hasPages())<div class="card-footer">{{ $documents->links() }}</div>@endif</div>
    </div></div>
@endsection
