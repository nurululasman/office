@extends('layouts.back.master')

@section('title', 'Terbitkan Nomor Dokumen')

@section('content')
    <div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Register Dokumen</div><h1 class="page-title">Terbitkan nomor surat umum</h1></div>
    </div></div></div>
    <div class="page-body"><div class="container-xl"><div class="row justify-content-center"><div class="col-lg-8">
        <form method="POST" action="{{ route('documents.store') }}" class="card" onsubmit="return confirm('Terbitkan nomor sekarang? Nomor yang sudah terbit tidak dapat digunakan ulang.')">
            @csrf
            <div class="card-header"><h2 class="card-title">Data dokumen</h2></div>
            <div class="card-body">
                @if($documentTypes->isEmpty())
                    <div class="alert alert-warning">Belum ada tipe dokumen aktif. Hubungi administrator dokumen sebelum menerbitkan nomor.</div>
                @endif
                <div class="mb-3"><label class="form-label" for="document_type_id">Tipe dokumen</label>
                    <select id="document_type_id" name="document_type_id" class="form-select" required @disabled($documentTypes->isEmpty())>
                        <option value="">Pilih tipe dokumen</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->getKey() }}" @selected(old('document_type_id') === $type->getKey())>{{ $type->name }} ({{ $type->code }}) — {{ $type->number_pattern }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3"><label class="form-label" for="title">Judul</label><input id="title" name="title" class="form-control" maxlength="255" required value="{{ old('title') }}" placeholder="Contoh: Surat Keterangan Operasional"></div>
                <div class="mb-0"><label class="form-label" for="purpose">Peruntukan</label><textarea id="purpose" name="purpose" class="form-control" rows="5" maxlength="5000" required placeholder="Jelaskan tujuan atau penerima dokumen">{{ old('purpose') }}</textarea><div class="form-hint">Periksa kembali data sebelum menerbitkan. Nomor terbit bersifat permanen; koreksi dilakukan melalui void.</div></div>
            </div>
            <div class="card-footer text-end"><button type="submit" class="btn btn-primary" @disabled($documentTypes->isEmpty())>Terbitkan nomor</button></div>
        </form>
    </div></div></div></div>
@endsection
