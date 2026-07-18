<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview Draft · {{ $quotation->subject }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e8edf3; color: #172433; font: 12px/1.5 Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 3; padding: 10px; background: #172433; color: white; text-align: center; }
        .toolbar button { padding: 7px 16px; cursor: pointer; }
        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 18px auto; padding: 16mm; overflow: hidden; background: white; box-shadow: 0 3px 18px #0003; }
        .watermark { position: absolute; inset: 42% auto auto 11%; z-index: 0; transform: rotate(-35deg); color: #d9dee5; font-size: 92px; font-weight: 700; letter-spacing: 16px; opacity: .58; }
        .content { position: relative; z-index: 1; }
        h1 { margin: 0 0 3px; color: #195d97; font-size: 21px; }
        .muted { color: #65758b; }
        .rule { height: 3px; margin: 10px 0 20px; background: #287cb8; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; margin-bottom: 20px; }
        .meta-row { display: grid; grid-template-columns: 90px 8px 1fr; }
        table { width: 100%; margin: 18px 0; border-collapse: collapse; }
        th, td { padding: 7px 8px; border: 1px solid #596b7d; vertical-align: top; }
        th { background: #dceaf5; text-align: center; }
        td.number { text-align: right; white-space: nowrap; }
        .terms { margin-top: 18px; padding-left: 20px; }
        .signature { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; margin-top: 38px; }
        .signature-space { height: 70px; }
        @media print { body { background: white; } .toolbar { display: none; } .sheet { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
<div class="toolbar">Preview draft — bukan dokumen resmi <button type="button" onclick="window.print()">Cetak preview</button></div>
<main class="sheet" data-testid="quotation-preview">
    <div class="watermark" aria-label="Draft">DRAFT</div>
    <div class="content">
        <header><h1>{{ $quotation->template->companyProfile->legal_name }}</h1><div class="muted">{{ implode(', ', $quotation->template->companyProfile->address_lines) }} · {{ $quotation->template->companyProfile->city }}</div><div class="rule"></div></header>
        <section class="meta">
            <div><div class="meta-row"><strong>Quotation</strong><span>:</span><span>DRAFT</span></div><div class="meta-row"><strong>To</strong><span>:</span><span>{{ $quotation->customer_name }}</span></div><div class="meta-row"><strong>Attention</strong><span>:</span><span>{{ $quotation->attention_name ?: '—' }}{{ $quotation->attention_role ? ' · '.$quotation->attention_role : '' }}</span></div><div class="meta-row"><strong>Subject</strong><span>:</span><span>{{ $quotation->subject }}</span></div></div>
            <div><div class="meta-row"><strong>Date</strong><span>:</span><span>{{ $formatter->date($quotation->quotation_date->toDateString()) }}</span></div><div class="meta-row"><strong>From</strong><span>:</span><span>{{ $quotation->sender_name }}</span></div><div class="meta-row"><strong>Title</strong><span>:</span><span>{{ $quotation->sender_title }}</span></div></div>
        </section>
        @if($quotation->intro_text)<p>{{ $quotation->intro_text }}</p>@endif
        <table><thead><tr><th>No</th>@foreach($quotation->item_schema['columns'] ?? [] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>@foreach($quotation->items as $item)<tr><td class="number">{{ $item->position }}</td>@foreach($item->values as $value)<td class="{{ in_array($value->value_type, ['decimal', 'integer', 'currency']) ? 'number' : '' }}">{{ $formatter->format($value->value, $value->value_type, $quotation->currency) }}</td>@endforeach</tr>@endforeach</tbody></table>
        @if($quotation->terms->isNotEmpty())<strong>Terms:</strong><ul class="terms">@foreach($quotation->terms as $term)<li>{{ $term->content }}</li>@endforeach</ul>@endif
        @if($quotation->closing_text)<p>{{ $quotation->closing_text }}</p>@endif
        <section class="signature"><div>Sincerely Yours,<div class="signature-space"></div><strong>{{ $quotation->sender_name }}</strong><br>{{ $quotation->sender_title }}</div><div>Approved By,<div class="signature-space"></div><span class="muted">Name & signature</span></div></section>
    </div>
</main>
</body>
</html>
