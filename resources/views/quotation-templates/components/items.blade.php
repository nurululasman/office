<table class="rate-table">
    <colgroup><col style="width: 10mm">@foreach($tableLayout['columns'] as $column)<col @if($column['width']) style="width: {{ $column['width'] }}%" @endif>@endforeach</colgroup>
    <thead>
        <tr><th class="sequence" @if($tableLayout['has_grouped_header']) rowspan="2" @endif>No</th>@foreach($tableLayout['header_blocks'] as $block)<th @if($block['grouped']) colspan="{{ count($block['columns']) }}" @elseif($tableLayout['has_grouped_header']) rowspan="2" @endif>{{ $block['label'] }}</th>@endforeach</tr>
        @if($tableLayout['has_grouped_header'])<tr>@foreach($tableLayout['header_blocks'] as $block)@if($block['grouped'])@foreach($block['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach @endif @endforeach</tr>@endif
    </thead>
    <tbody>@foreach($quotation->items as $item)@php($values = $item->values->keyBy('key'))<tr><td class="sequence">{{ $item->position }}</td>@foreach($tableLayout['columns'] as $column)@php($value = $values->get($column['key']))<td class="align-{{ $column['align'] }} {{ in_array($column['value_type'], ['decimal', 'integer', 'currency']) ? 'typed-number' : '' }}">{{ $formatter->format($value?->value, $column['value_type'], $quotation->currency) }}</td>@endforeach</tr>@endforeach</tbody>
</table>
