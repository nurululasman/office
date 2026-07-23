@extends('layouts.back.master')

@section('title', $template->name)

@push('styles')
<style>
    .template-preview-shell { overflow: auto; padding: 16px; background: #e6ebf0; }
    .template-preview-page { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 17mm 18mm 22mm; background: #fff; color: #111820; box-shadow: 0 4px 22px #17243330; font: 10.5pt/1.38 Arial, Helvetica, sans-serif; }
    .template-preview-page > :first-child { margin-top: 0; }
    .template-preview-page table { max-width: 100%; }
    .template-preview-page .rate-table { width: 100%; margin: 0 0 3mm; border-collapse: collapse; table-layout: fixed; }
    .template-preview-page .rate-table th, .template-preview-page .rate-table td { padding: 0 2mm 1.8mm; border: .35mm solid #1c252d; vertical-align: middle; overflow-wrap: anywhere; }
    .template-preview-page .rate-table th { background: #e5f3f8; font-weight: 700; text-align: center; }
    .template-preview-page .rate-table .sequence { width: 10mm; text-align: center; }
    .template-preview-page .align-left { text-align: left; }
    .template-preview-page .align-center { text-align: center; }
    .template-preview-page .align-right { text-align: right; }
    .template-preview-page .typed-number { white-space: nowrap; }
    .template-preview-page .quotation-item-list, .template-preview-page .terms { padding-left: 7mm; }
    .template-preview-page .signatures { display: flex; gap: 30mm; margin-top: 4mm; }
    .template-preview-page .signatures > div { flex: 1 1 0; }
    .template-preview-page .signature-space { height: 18mm; }
    .template-preview-page .signature-name { font-weight: 700; }
    .template-preview-page .company-logo { display: flex; min-height: 24mm; align-items: center; justify-content: flex-end; }
    .template-preview-page .company-logo img { width: 38mm; height: 32mm; object-fit: contain; }
    .template-preview-page .draft-watermark { position: absolute; top: 43%; left: 12%; transform: rotate(-34deg); color: #d8dde2; font-size: 68pt; font-weight: 700; opacity: .46; }
</style>
@endpush

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Template Quotation</div>
                <h1 class="page-title">{{ $template->name }} · v{{ $template->version }}</h1>
            </div>
            <div class="col-auto ms-auto">
                <div class="btn-list">
                    @can('update', $template)
                        <a class="btn btn-primary" href="{{ route('quotation-templates.edit', $template) }}">Ubah draft</a>
                    @endcan
                    @can('createVersion', $template)
                        <form method="POST" action="{{ route('quotation-templates.duplicate', $template) }}">
                            @csrf
                            <button class="btn" type="submit">Buat versi baru</button>
                        </form>
                    @endcan
                    @can('activate', $template)
                        <form method="POST" action="{{ route('quotation-templates.activate', $template) }}">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $template->lock_version }}">
                            <button class="btn btn-success" type="submit">Aktifkan</button>
                        </form>
                    @endcan
                    @can('archive', $template)
                        <form method="POST" action="{{ route('quotation-templates.archive', $template) }}" onsubmit="return confirm('Arsipkan template ini?')">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $template->lock_version }}">
                            <button class="btn btn-outline-danger" type="submit">Archive</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Metadata</h2></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Key</dt><dd class="col-7"><code>{{ $template->template_key }}</code></dd>
                            <dt class="col-5">Status</dt><dd class="col-7">{{ ucfirst($template->status) }}</dd>
                            <dt class="col-5">Perusahaan</dt><dd class="col-7">{{ $template->companyProfile->display_name }}</dd>
                            <dt class="col-5">Checksum</dt><dd class="col-7 text-break"><code>{{ $template->content_sha256 }}</code></dd>
                            <dt class="col-5">Lock version</dt><dd class="col-7">{{ $template->lock_version }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="card-title">Preview visual</h2>
                    </div>
                    <div class="card-body p-0">
                        @if($previewHtml)
                            <div class="template-preview-shell">
                                <article class="template-preview-page" data-template-preview="true">{!! $previewHtml !!}</article>
                            </div>
                        @else
                            <div class="alert alert-warning m-3">
                                <h3 class="alert-heading">Preview belum dapat dirender</h3>
                                <p class="mb-0">{{ $previewError }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer text-secondary">
                        Preview menggunakan sample data dan renderer yang sama dengan quotation. Tidak ada data quotation yang disimpan.
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h2 class="card-title">Sumber konten tersanitasi</h2></div>
                    <div class="card-body"><pre class="text-wrap mb-0">{{ $template->content_html }}</pre></div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Item schema</h2></div>
                    <div class="card-body"><pre class="text-wrap mb-0">{{ json_encode($template->item_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
