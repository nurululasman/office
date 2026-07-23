@extends('layouts.back.master')

@php($isEdit = $template->exists)
@section('title', $isEdit ? 'Ubah Template Quotation' : 'Buat Template Quotation')

@section('content')
<div class="page-header"><div class="container-xl"><div class="page-pretitle">Template Quotation</div><h1 class="page-title">{{ $isEdit ? 'Ubah draft '.$template->name : 'Buat template quotation' }}</h1></div></div>
<div class="page-body"><div class="container-xl"><form class="card" method="POST" action="{{ $isEdit ? route('quotation-templates.update', $template) : route('quotation-templates.store') }}">
    @csrf @if($isEdit) @method('PUT') <input type="hidden" name="lock_version" value="{{ $template->lock_version }}"> @endif
    <div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Nama</label><input class="form-control" id="name" name="name" maxlength="255" required value="{{ old('name', $template->name) }}"></div>
        <div class="col-md-6"><label class="form-label" for="template_key">Template key</label><input class="form-control" id="template_key" name="template_key" maxlength="100" required value="{{ old('template_key', $template->template_key) }}" @readonly($isEdit)><div class="form-hint">Huruf kecil, angka, dan tanda hubung. Tidak berubah setelah dibuat.</div></div>
        <div class="col-md-6"><label class="form-label" for="company_profile_id">Profil perusahaan</label><select class="form-select" id="company_profile_id" name="company_profile_id" required>@foreach($companyProfiles as $profile)<option value="{{ $profile->getKey() }}" @selected(old('company_profile_id', $template->company_profile_id) === $profile->getKey())>{{ $profile->display_name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Versi dan status</label><input class="form-control" disabled value="{{ $isEdit ? 'v'.$template->version.' · '.$template->status : 'v1 · draft' }}"></div>
        <div class="col-12">
            <label class="form-label" for="content_html">Konten dokumen</label>
            <textarea class="form-control font-monospace" id="content_html" name="content_html" rows="18" required aria-describedby="content_html_hint">{{ old('content_html', $template->content_html) }}</textarea>
            <div class="form-hint" id="content_html_hint">Gunakan editor untuk menyusun konten. Jika JavaScript gagal dimuat, field tetap dapat diedit sebagai HTML source.</div>
            <div class="alert alert-warning mt-2 d-none" id="tinymce-fallback" role="status">Editor visual tidak dapat dimuat. Anda masih dapat menyimpan melalui HTML source pada textarea.</div>
        </div>
        <div class="col-12"><label class="form-label" for="item_schema_json">Item schema JSON</label><textarea class="form-control font-monospace" id="item_schema_json" name="item_schema_json" rows="10" required>{{ old('item_schema_json', json_encode($template->item_schema ?? ['columns' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea><div class="form-hint">Presentation dapat menggunakan <code>table</code> (default), <code>list</code>, atau <code>nested_list</code>. Mode list memerlukan <code>content_key</code>; nested list mendukung <code>max_depth</code> 2–5.</div></div>
        <div class="col-md-6"><label class="form-label" for="default_intro_text">Default pengantar</label><textarea class="form-control" id="default_intro_text" name="default_intro_text" rows="4">{{ old('default_intro_text', $template->default_intro_text) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="default_closing_text">Default penutup</label><textarea class="form-control" id="default_closing_text" name="default_closing_text" rows="4">{{ old('default_closing_text', $template->default_closing_text) }}</textarea></div>
        <div class="col-12"><label class="form-label" for="default_terms_text">Default terms</label><textarea class="form-control" id="default_terms_text" name="default_terms_text" rows="5">{{ old('default_terms_text', implode(PHP_EOL, $template->default_terms ?? [])) }}</textarea><div class="form-hint">Satu term per baris.</div></div>
    </div></div>
    <div class="card-footer text-end"><a class="btn" href="{{ $isEdit ? route('quotation-templates.show', $template) : route('quotation-templates.index') }}">Batal</a><button class="btn btn-primary">Simpan draft</button></div>
</form></div></div>
@endsection

@push('scripts')
<script src="{{ asset('libs/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('js/quotation-template-editor.js') }}"></script>
@endpush
