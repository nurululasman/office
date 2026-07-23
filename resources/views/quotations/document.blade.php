@php
    $snapshotCompany = is_array($quotation->template_snapshot['company_profile'] ?? null)
        ? $quotation->template_snapshot['company_profile']
        : [];
    $primaryColor = is_string($snapshotCompany['primary_color'] ?? null)
        && preg_match('/^#[0-9a-fA-F]{6}$/', $snapshotCompany['primary_color'])
            ? $snapshotCompany['primary_color']
            : '#087eae';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isDraft ? 'Preview Draft' : 'Quotation' }} - {{ $quotation->subject }}</title>
    <style>
        @page { size: A4 portrait; margin: 17mm 18mm 16mm; }
        :root { --brand-primary: {{ $primaryColor }}; --ink: #111820; --muted: #586471; --paper: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e6ebf0; color: var(--ink); font: 10.5pt/1.38 Arial, Helvetica, sans-serif; }
        hr { margin:3mm 0 3mm; border: 0; border-top: .45mm solid {{ $primaryColor }}; }
        .preview-toolbar { position: sticky; top: 0; z-index: 20; padding: 10px 16px; background: #16212c; color: #fff; text-align: center; }
        .preview-toolbar button { margin-left: 12px; padding: 7px 16px; border: 0; border-radius: 4px; cursor: pointer; }
        .quotation-page { position: relative; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 17mm 18mm 22mm; background: var(--paper); box-shadow: 0 4px 22px #17243330; }
        .quotation-content { position: relative; z-index: 1; overflow-wrap: anywhere; }
        .quotation-content > :first-child { margin-top: 0; }
        .quotation-content h1, .quotation-content h2, .quotation-content h3, .quotation-content h4 { break-after: avoid; page-break-after: avoid; }
        .quotation-content table { max-width: 100%; }
        .quotation-content tr, .quotation-content li { break-inside: avoid; page-break-inside: avoid; }
        .quotation-content .page-break { break-before: page; page-break-before: always; }
        .draft-watermark { position: fixed; top: 43%; left: 12%; z-index: 0; transform: rotate(-34deg); color: #d8dde2; font-size: 68pt; font-weight: 700; letter-spacing: 9mm; opacity: .46; }
        .company-logo { display: flex; align-items: center; justify-content: flex-end; min-height: 24mm; }
        .company-logo img { display: block; width: 38mm; height: 32mm; object-fit: contain; object-position: center; }
        .rate-table { width: 100%; margin: 0 0 3mm; border-collapse: collapse; table-layout: fixed; }
        .rate-table thead { display: table-header-group; }
        .rate-table tbody { display: table-row-group; }
        .rate-table th, .rate-table td { padding: 0 2mm 1.8mm; border: .35mm solid #1c252d; vertical-align: middle; overflow-wrap: anywhere; }
        .rate-table th { background: color-mix(in srgb, var(--brand-primary) 14%, white); font-weight: 700; text-align: center; }
        .rate-table .sequence { width: 10mm; text-align: center; }
        .rate-table .align-left { text-align: left; }
        .rate-table .align-center { text-align: center; }
        .rate-table .align-right { text-align: right; }
        .rate-table .typed-number { white-space: nowrap; }
        .quotation-item-list { margin: 0 0 3mm; padding-left: 7mm; }
        .quotation-item-list .quotation-item-list { margin: 1mm 0 0; }
        .terms { margin: 0 0 4mm; padding-left: 7mm; }
        .signatures { display: flex; gap: 30mm; margin-top: 4mm; break-inside: avoid; page-break-inside: avoid; }
        .signatures > div { flex: 1 1 0; }
        .signature-space { height: 18mm; }
        .signature-name { font-weight: 700; }
        .document-footer { position: absolute; right: 18mm; bottom: 10mm; left: 18mm; color: var(--muted); font-size: 8pt; text-align: center; }
        @media print {
            body { background: #fff; }
            .preview-toolbar { display: none !important; }
            .quotation-page { width: auto; min-height: 254mm; margin: 0; padding: 0 0 6mm; box-shadow: none; }
            .document-footer { position: static; margin-top: 6mm; transform: none; }
        }
    </style>
</head>
<body>
@if($browserPreview ?? false)
    <div class="preview-toolbar">Preview draft - bukan dokumen resmi <button type="button" onclick="window.print()">Cetak preview</button></div>
@endif
<main class="quotation-page" data-testid="quotation-preview">
    <article class="quotation-content" data-template-rendered="true">{!! $renderedHtml !!}</article>
    <footer class="document-footer">{{ $isDraft ? 'DRAFT - NOT AN OFFICIAL DOCUMENT' : $quotation->document?->number }}</footer>
</main>
</body>
</html>
