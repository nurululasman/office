@extends('layouts.back.master')

@section('title', 'Nomor Berhasil Diterbitkan')

@section('content')
    <div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Register Dokumen</div><h1 class="page-title">Nomor berhasil diterbitkan</h1></div>
        <div class="col-auto ms-auto btn-list">@can('view', $document)<a href="{{ route('documents.show', $document) }}" class="btn">Lihat detail</a>@endcan @can('create', App\Models\Document::class)<a href="{{ route('documents.create') }}" class="btn btn-primary">Terbitkan nomor lain</a>@endcan</div>
    </div></div></div>
    <div class="page-body"><div class="container-xl"><div class="row justify-content-center"><div class="col-lg-8">
        <div class="card" x-data="{ copied: false, failed: false, async copyNumber() { try { await navigator.clipboard.writeText(this.$refs.number.textContent.trim()); this.copied = true; this.failed = false; setTimeout(() => this.copied = false, 2000); } catch (error) { this.failed = true; } } }">
            <div class="card-body text-center py-5">
                <span class="badge bg-success-lt mb-3">Nomor terbit</span>
                <div class="text-secondary">{{ $document->documentType->name }}</div>
                <div class="display-6 my-3 text-break user-select-all" x-ref="number" data-testid="issued-number">{{ $document->number }}</div>
                <button type="button" class="btn btn-primary" @click="copyNumber"><span x-text="copied ? 'Tersalin' : 'Salin nomor'">Salin nomor</span></button>
                <div x-show="failed" class="text-danger mt-2">Clipboard tidak tersedia. Pilih nomor di atas lalu salin secara manual.</div>
            </div>
            <div class="card-table table-responsive"><table class="table table-vcenter mb-0"><tbody>
                <tr><th class="w-25">Judul</th><td>{{ $document->title }}</td></tr>
                <tr><th>Peruntukan</th><td class="text-wrap">{{ $document->purpose }}</td></tr>
                <tr><th>Periode</th><td>{{ $document->period_year }}</td></tr>
                <tr><th>Diterbitkan</th><td>{{ $document->issued_at->timezone(config('office.business_timezone'))->format('d M Y H:i') }} WIB oleh {{ $document->issuer->name }}</td></tr>
            </tbody></table></div>
        </div>
    </div></div></div></div>
@endsection
