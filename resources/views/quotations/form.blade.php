@extends('layouts.back.master')

@section('title', $quotation->exists ? 'Edit Draft Quotation' : 'Buat Draft Quotation')

@section('content')
@php
    $isEdit = $quotation->exists;
    $selected = old('template_id', $selectedTemplateId ?: $templates->first()?->getKey());
    $templateSettings = $templates->mapWithKeys(fn ($template) => [$template->getKey() => [
        'defaults' => [
            'intro_text' => $template->default_intro_text,
            'closing_text' => $template->default_closing_text,
            'terms' => $template->default_terms,
        ],
    ]]);
    if ($quotation->exists) {
        $templateSettings[$quotation->template_id] = [
            'defaults' => ['intro_text' => null, 'closing_text' => null, 'terms' => []],
        ];
    }
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="page-pretitle">Quotation</div><h1 class="page-title">{{ $isEdit ? 'Edit draft quotation' : 'Buat draft quotation' }}</h1></div></div>
<div class="page-body"><div class="container-xl">
<form method="POST" action="{{ $isEdit ? route('quotations.update', $quotation) : route('quotations.store') }}" id="quotation-form">
    @csrf @if($isEdit) @method('PUT') @endif
    @if($isEdit)<input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">@endif
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Identitas quotation</h2></div><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="template_id">Template aktif</label><select class="form-select" id="template_id" name="template_id" required @disabled($templates->isEmpty())><option value="">Pilih template</option>@foreach($templates as $template)<option value="{{ $template->getKey() }}" @selected($selected === $template->getKey())>{{ $template->name }} · v{{ $template->version }} · {{ $template->companyProfile->display_name }}</option>@endforeach</select><div class="form-hint">Versi template dan branding disimpan sebagai snapshot quotation.</div></div>
        <div class="col-md-3"><label class="form-label" for="quotation_date">Tanggal</label><input class="form-control" type="date" id="quotation_date" name="quotation_date" value="{{ old('quotation_date', $quotation->quotation_date?->format('Y-m-d')) }}" required></div>
        <div class="col-md-3"><label class="form-label" for="currency">Mata uang</label><input class="form-control" id="currency" name="currency" maxlength="3" value="{{ old('currency', $quotation->currency ?: 'IDR') }}" required></div>
        <div class="col-12"><label class="form-label" for="subject">Subjek</label><input class="form-control" id="subject" name="subject" maxlength="255" value="{{ old('subject', $quotation->subject) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="customer_name">Pelanggan</label><input class="form-control" id="customer_name" name="customer_name" maxlength="255" value="{{ old('customer_name', $quotation->customer_name) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="customer_address">Alamat pelanggan</label><textarea class="form-control" id="customer_address" name="customer_address" required>{{ old('customer_address', $quotation->customer_address) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="attention_name">Attention</label><input class="form-control" id="attention_name" name="attention_name" value="{{ old('attention_name', $quotation->attention_name) }}"></div>
        <div class="col-md-6"><label class="form-label" for="attention_role">Jabatan attention</label><input class="form-control" id="attention_role" name="attention_role" value="{{ old('attention_role', $quotation->attention_role) }}"></div>
        <div class="col-md-6"><label class="form-label" for="sender_name">Pengirim</label><input class="form-control" id="sender_name" name="sender_name" value="{{ old('sender_name', $quotation->sender_name ?: auth()->user()->name) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="sender_title">Jabatan pengirim</label><input class="form-control" id="sender_title" name="sender_title" value="{{ old('sender_title', $quotation->sender_title) }}"></div>
        <div class="col-md-6"><label class="form-label" for="intro_text">Pengantar</label><textarea class="form-control" id="intro_text" name="intro_text">{{ old('intro_text', $quotation->intro_text ?? $templates->firstWhere('id', $selected)?->default_intro_text) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="closing_text">Penutup</label><textarea class="form-control" id="closing_text" name="closing_text">{{ old('closing_text', $quotation->closing_text ?? $templates->firstWhere('id', $selected)?->default_closing_text) }}</textarea></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Item quotation</h2></div><div class="card-body"><label class="form-label" for="content_html">Isi item</label><textarea class="form-control" id="content_html" name="content_html" rows="20" required data-editor-mode="item">{{ old('content_html', $quotation->exists ? $quotation->content_html : '') }}</textarea><div class="form-hint">Editor ini hanya untuk item quotation. Buat tabel, daftar, dan format item secara bebas; hasilnya disimpan sebagai HTML yang telah disanitasi.</div><div class="alert alert-warning mt-2 d-none" id="tinymce-fallback" role="status">Editor visual tidak dapat dimuat. Konten item masih dapat diedit sebagai HTML source.</div></div></div>
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Terms quotation</h2></div><div class="card-body"><label class="form-label" for="terms_html">Isi terms</label><textarea class="form-control" id="terms_html" name="terms_html" rows="12" required data-editor-mode="item">{{ old('terms_html', $quotation->exists ? $quotation->terms_html : '') }}</textarea><div class="form-hint">Editor ini hanya untuk terms quotation. Gunakan daftar, tabel, atau format HTML lain sesuai kebutuhan.</div></div></div>
    <div class="text-end"><button class="btn me-2" type="submit" name="submit_action" value="preview" @disabled($templates->isEmpty())>Simpan &amp; preview</button><button class="btn btn-primary" type="submit" name="submit_action" value="save" @disabled($templates->isEmpty())>Simpan draft</button></div>
</form></div></div>
@endsection

@push('scripts')
<script src="{{ asset('libs/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('js/quotation-template-editor.js') }}"></script>
<script>
(() => {
    const templates = @json($templateSettings);
    const templateSelect = document.getElementById('template_id');
    const intro = document.getElementById('intro_text');
    const closing = document.getElementById('closing_text');
    templateSelect.addEventListener('change', () => {
        const defaults = templates[templateSelect.value]?.defaults ?? {};
        intro.value = defaults.intro_text ?? '';
        closing.value = defaults.closing_text ?? '';
        const editor = window.tinymce?.get('content_html');
        const termsEditor = window.tinymce?.get('terms_html');
        if (editor) editor.setContent('');
        else document.getElementById('content_html').value = '';
        if (termsEditor) termsEditor.setContent('');
        else document.getElementById('terms_html').value = '';
    });
})()
</script>
@endpush
