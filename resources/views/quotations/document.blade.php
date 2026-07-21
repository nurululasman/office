<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isDraft ? 'Preview Draft' : 'Quotation' }} - {{ $quotation->subject }}</title>
    <style>
        @page { size: A4 portrait; margin: 17mm 18mm 16mm; }
        :root { --brand-blue: #087eae; --ink: #111820; --muted: #586471; --paper: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e6ebf0; color: var(--ink); font: 10.5pt/1.38 Arial, Helvetica, sans-serif; }
        .preview-toolbar { position: sticky; top: 0; z-index: 20; padding: 10px 16px; background: #16212c; color: #fff; text-align: center; }
        .preview-toolbar button { margin-left: 12px; padding: 7px 16px; border: 0; border-radius: 4px; cursor: pointer; }
        .quotation-page { position: relative; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 17mm 18mm 16mm; background: var(--paper); box-shadow: 0 4px 22px #17243330; }
        .quotation-content { position: relative; z-index: 1; }
        .draft-watermark { position: absolute; top: 43%; left: 12%; z-index: 0; transform: rotate(-34deg); color: #d8dde2; font-size: 68pt; font-weight: 700; letter-spacing: 9mm; opacity: .46; }
        .letterhead { display: grid; grid-template-columns: minmax(0, 1fr) 42mm; align-items: end; gap: 10mm; min-height: 34mm; }
        .company-name { margin: 0 0 1.5mm; font-size: 13pt; font-weight: 700; letter-spacing: .05pt; }
        .company-contact { margin: 0; line-height: 1.38; }
        .company-contact div { min-height: 4.5mm; }
        .company-logo { display: flex; align-items: center; justify-content: flex-end; height: 34mm; }
        .company-logo img { display: block; width: 38mm; height: 32mm; object-fit: contain; object-position: center; }
        .brand-rule { height: .75mm; margin: 3.5mm 0 8mm; background: var(--brand-blue); }
        .document-meta { display: grid; grid-template-columns: 62% 38%; column-gap: 8mm; margin-bottom: 3.5mm; }
        .meta-row { display: grid; grid-template-columns: 27mm 4mm minmax(0, 1fr); min-height: 6mm; }
        .meta-row .label { font-weight: 500; }
        .subject-rule { height: .35mm; margin: 1.5mm 0 5mm; background: #222; }
        .intro { margin: 0 0 11mm; }
        .intro p { margin: 0 0 1mm; }
        .rate-table { width: 100%; margin: 0 0 8mm; border-collapse: collapse; table-layout: fixed; }
        .rate-table thead { display: table-header-group; }
        .rate-table tbody { display: table-row-group; }
        .rate-table tr { break-inside: avoid; page-break-inside: avoid; }
        .rate-table th, .rate-table td { padding: 1.8mm 2mm; border: .35mm solid #1c252d; vertical-align: middle; overflow-wrap: anywhere; }
        .rate-table th { background: #dceeea; font-weight: 700; text-align: center; }
        .rate-table .sequence { width: 10mm; text-align: center; }
        .rate-table .align-left { text-align: left; }
        .rate-table .align-center { text-align: center; }
        .rate-table .align-right { text-align: right; }
        .rate-table .typed-number { white-space: nowrap; }
        .terms { margin: 0 0 11mm; padding-left: 7mm; break-inside: avoid; page-break-inside: avoid; }
        .terms li { margin-bottom: 2mm; padding-left: 2mm; }
        .closing { margin: 0 0 12mm; break-inside: avoid; page-break-inside: avoid; }
        .closing p { margin: 0 0 7mm; }
        .closing .emphasis { font-weight: 700; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; column-gap: 30mm; margin-top: 8mm; break-inside: avoid; page-break-inside: avoid; }
        .completion-block { break-inside: avoid; page-break-inside: avoid; }
        .signature-heading { font-size: 11pt; }
        .signature-space { height: 25mm; }
        .signature-name { font-weight: 700; }
        .document-footer { position: absolute; right: 18mm; bottom: 8mm; left: 18mm; color: var(--muted); font-size: 8pt; text-align: center; }
        @media print {
            body { background: #fff; }
            .preview-toolbar { display: none !important; }
            .quotation-page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
            .document-footer { bottom: -10mm; }
        }
    </style>
</head>
<body>
@if($browserPreview ?? false)
    <div class="preview-toolbar">Preview draft - bukan dokumen resmi <button type="button" onclick="window.print()">Cetak preview</button></div>
@endif
<main class="quotation-page" data-testid="quotation-preview">
    @if($isDraft)<div class="draft-watermark" aria-label="Draft">DRAFT</div>@endif
    <div class="quotation-content">
        <header class="letterhead">
            <div>
                <h1 class="company-name">{{ strtoupper($quotation->template->companyProfile->legal_name) }}</h1>
                <div class="company-contact">
                    @foreach($quotation->template->companyProfile->address_lines as $line)<div>{{ $line }}</div>@endforeach
                    @if($quotation->template->companyProfile->email)<div>Email: {{ $quotation->template->companyProfile->email }}</div>@endif
                </div>
            </div>
            <div class="company-logo"><img src="{{ $logoSource }}" width="144" height="121" alt="Logo {{ $quotation->template->companyProfile->display_name }}"></div>
        </header>
        <div class="brand-rule"></div>

        <section class="document-meta">
            <div>
                <div class="meta-row"><span class="label">Quotation</span><span>:</span><span>{{ $quotation->document?->number ?? 'DRAFT' }}</span></div>
                <div class="meta-row"><span class="label">To</span><span>:</span><span>{{ $quotation->customer_name }}</span></div>
                @if($quotation->attention_name)<div class="meta-row"><span></span><span></span><span>{{ $quotation->attention_name }}{{ $quotation->attention_role ? ' - '.$quotation->attention_role : '' }}</span></div>@endif
                <div class="meta-row"><span class="label">Subject</span><span>:</span><span>{{ $quotation->subject }}</span></div>
            </div>
            <div>
                <div class="meta-row"><span class="label">Date</span><span>:</span><span>{{ $formatter->date($quotation->quotation_date->toDateString()) }}</span></div>
                <div class="meta-row"><span class="label">From</span><span>:</span><span>{{ $quotation->sender_name }}</span></div>
            </div>
        </section>
        <div class="subject-rule"></div>

        <section class="intro">
            @if($quotation->attention_name)<p>Dear {{ $quotation->attention_name }},</p>@endif
            <p>{{ $quotation->intro_text ?: 'We are pleased to submit our official quotation proposal as follows:' }}</p>
        </section>

        <table class="rate-table">
            <colgroup><col style="width: 10mm">@foreach($tableLayout['columns'] as $column)<col @if($column['width']) style="width: {{ $column['width'] }}%" @endif>@endforeach</colgroup>
            <thead>
                <tr><th class="sequence" @if($tableLayout['has_grouped_header']) rowspan="2" @endif>No</th>@foreach($tableLayout['header_blocks'] as $block)<th @if($block['grouped']) colspan="{{ count($block['columns']) }}" @elseif($tableLayout['has_grouped_header']) rowspan="2" @endif>{{ $block['label'] }}</th>@endforeach</tr>
                @if($tableLayout['has_grouped_header'])<tr>@foreach($tableLayout['header_blocks'] as $block)@if($block['grouped'])@foreach($block['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach @endif @endforeach</tr>@endif
            </thead>
            <tbody>@foreach($quotation->items as $item)@php($values = $item->values->keyBy('key'))<tr><td class="sequence">{{ $item->position }}</td>@foreach($tableLayout['columns'] as $column)@php($value = $values->get($column['key']))<td class="align-{{ $column['align'] }} {{ in_array($column['value_type'], ['decimal', 'integer', 'currency']) ? 'typed-number' : '' }}">{{ $formatter->format($value?->value, $column['value_type'], $quotation->currency) }}</td>@endforeach</tr>@endforeach</tbody>
        </table>

        @if($quotation->terms->isNotEmpty())<ul class="terms">@foreach($quotation->terms as $term)<li>{{ $term->content }}</li>@endforeach</ul>@endif
        <div class="completion-block">
            <section class="closing">
                <p class="emphasis">Thank you for giving {{ $quotation->template->companyProfile->display_name }} the opportunity of quoting on your business.</p>
                <p>{{ $quotation->closing_text ?: 'Hope the above can meet your requirements. Looking forward to receiving your valuable order.' }}</p>
            </section>
            <section class="signatures">
                <div><div class="signature-heading">Sincerely Yours,</div><div class="signature-space"></div><div class="signature-name">{{ $quotation->sender_name }}</div><div>{{ $quotation->sender_title }}</div></div>
                <div><div class="signature-heading">Approved By,</div><div class="signature-space"></div><div class="signature-name">{{ $quotation->attention_name ? $quotation->attention_name : $quotation->customer_name }}</div></div>
            </section>
        </div>
    </div>
    <footer class="document-footer">{{ $isDraft ? 'DRAFT - NOT AN OFFICIAL DOCUMENT' : $quotation->document?->number }}</footer>
</main>
</body>
</html>
