@extends('layouts.back.master')

@section('title', $quotation->exists ? 'Edit Draft Quotation' : 'Buat Draft Quotation')

@section('content')
@php
    $isEdit = $quotation->exists;
    $selected = old('template_id', $selectedTemplateId ?: $templates->first()?->getKey());
    $existingItems = old('items', $quotation->items?->map(fn ($item) => ['values' => $item->values->pluck('value', 'key')->all()])->all() ?: [['values' => []]]);
    $existingTerms = old('terms', $quotation->terms?->pluck('content')->all() ?: ['']);
    $templateSettings = $templates->mapWithKeys(fn ($template) => [$template->getKey() => $template->settings]);
    if ($quotation->exists) $templateSettings[$quotation->template_id] = $quotation->item_schema;
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="page-pretitle">Quotation</div><h1 class="page-title">{{ $isEdit ? 'Edit draft quotation' : 'Buat draft quotation' }}</h1></div></div>
<div class="page-body"><div class="container-xl">
<form method="POST" action="{{ $isEdit ? route('quotations.update', $quotation) : route('quotations.store') }}" id="quotation-form">
    @csrf @if($isEdit) @method('PUT') @endif
    @if($isEdit)<input type="hidden" name="lock_version" value="{{ $quotation->lock_version }}">@endif
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Identitas quotation</h2></div><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="template_id">Template</label><select class="form-select" id="template_id" name="template_id" required @disabled($templates->isEmpty())><option value="">Pilih template</option>@foreach($templates as $template)<option value="{{ $template->getKey() }}" @selected($selected === $template->getKey())>{{ $template->name }} · v{{ $template->version }} · {{ $template->companyProfile->display_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label" for="quotation_date">Tanggal</label><input class="form-control" type="date" id="quotation_date" name="quotation_date" value="{{ old('quotation_date', $quotation->quotation_date?->format('Y-m-d')) }}" required></div>
        <div class="col-md-3"><label class="form-label" for="currency">Mata uang</label><input class="form-control" id="currency" name="currency" maxlength="3" value="{{ old('currency', $quotation->currency ?: 'IDR') }}" required></div>
        <div class="col-12"><label class="form-label" for="subject">Subjek</label><input class="form-control" id="subject" name="subject" maxlength="255" value="{{ old('subject', $quotation->subject) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="customer_name">Pelanggan</label><input class="form-control" id="customer_name" name="customer_name" maxlength="255" value="{{ old('customer_name', $quotation->customer_name) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="customer_address">Alamat pelanggan</label><textarea class="form-control" id="customer_address" name="customer_address" required>{{ old('customer_address', $quotation->customer_address) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="attention_name">Attention</label><input class="form-control" id="attention_name" name="attention_name" value="{{ old('attention_name', $quotation->attention_name) }}"></div><div class="col-md-6"><label class="form-label" for="attention_role">Jabatan attention</label><input class="form-control" id="attention_role" name="attention_role" value="{{ old('attention_role', $quotation->attention_role) }}"></div>
        <div class="col-md-6"><label class="form-label" for="sender_name">Pengirim</label><input class="form-control" id="sender_name" name="sender_name" value="{{ old('sender_name', $quotation->sender_name ?: auth()->user()->name) }}" required></div><div class="col-md-6"><label class="form-label" for="sender_title">Jabatan pengirim</label><input class="form-control" id="sender_title" name="sender_title" value="{{ old('sender_title', $quotation->sender_title) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="intro_text">Pengantar</label><textarea class="form-control" id="intro_text" name="intro_text">{{ old('intro_text', $quotation->intro_text) }}</textarea></div><div class="col-md-6"><label class="form-label" for="closing_text">Penutup</label><textarea class="form-control" id="closing_text" name="closing_text">{{ old('closing_text', $quotation->closing_text) }}</textarea></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Item dinamis</h2><div class="card-actions"><button class="btn btn-sm" type="button" id="add-item">Tambah item</button></div></div><div class="card-body"><div id="items"></div></div></div>
    <div class="card mb-3"><div class="card-header"><h2 class="card-title">Terms</h2><div class="card-actions"><button class="btn btn-sm" type="button" id="add-term">Tambah term</button></div></div><div class="card-body"><div id="terms"></div></div></div>
    <div class="text-end"><button class="btn btn-primary" @disabled($templates->isEmpty())>Simpan draft</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const templates = @json($templateSettings);
    let initialItems = @json($existingItems);
    let initialTerms = @json($existingTerms);
    const templateSelect = document.getElementById('template_id');
    const items = document.getElementById('items');
    const terms = document.getElementById('terms');

    const escapeHtml = value => String(value ?? '').replace(/[&<>"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
    const inputType = type => type === 'date' ? 'date' : (['decimal', 'integer', 'currency'].includes(type) ? 'number' : 'text');
    function addItem(values = {}) {
        const index = items.children.length;
        const columns = templates[templateSelect.value]?.columns ?? [];
        const wrapper = document.createElement('div'); wrapper.className = 'border rounded p-3 mb-3 quotation-item';
        wrapper.innerHTML = `<div class="d-flex justify-content-between mb-2"><strong>Item ${index + 1}</strong><button type="button" class="btn btn-sm btn-outline-danger remove">Hapus</button></div><div class="row g-2">` + columns.map(column => `<div class="col-md-4"><label class="form-label">${escapeHtml(column.label)} <span class="text-secondary">(${escapeHtml(column.value_type)})</span></label><input class="form-control" type="${inputType(column.value_type)}" ${column.value_type === 'integer' ? 'step="1"' : (['decimal','currency'].includes(column.value_type) ? 'step="any"' : '')} name="items[${index}][values][${escapeHtml(column.key)}]" value="${escapeHtml(values[column.key])}" ${column.required ? 'required' : ''}></div>`).join('') + '</div>';
        wrapper.querySelector('.remove').addEventListener('click', () => { wrapper.remove(); renumberItems(); }); items.appendChild(wrapper);
    }
    function renumberItems() { [...items.children].forEach((row, index) => { row.querySelector('strong').textContent = `Item ${index + 1}`; row.querySelectorAll('input').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`)); }); }
    function addTerm(value = '') { const index = terms.children.length; const row = document.createElement('div'); row.className = 'input-group mb-2'; row.innerHTML = `<span class="input-group-text">${index + 1}</span><textarea class="form-control" name="terms[${index}]" maxlength="5000">${escapeHtml(value)}</textarea><button class="btn btn-outline-danger" type="button">Hapus</button>`; row.querySelector('button').onclick = () => { row.remove(); renumberTerms(); }; terms.appendChild(row); }
    function renumberTerms() { [...terms.children].forEach((row, index) => { row.querySelector('span').textContent = index + 1; row.querySelector('textarea').name = `terms[${index}]`; }); }
    templateSelect.addEventListener('change', () => { items.innerHTML = ''; addItem(); });
    document.getElementById('add-item').onclick = () => addItem(); document.getElementById('add-term').onclick = () => addTerm();
    initialItems.forEach(item => addItem(item.values ?? {})); initialTerms.forEach(addTerm);
})()
</script>
@endpush
