<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemValue;
use App\Models\QuotationTerm;
use App\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer;
use App\Services\DocumentTemplates\QuotationTemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$caseArgument = collect($arguments)->first(fn (string $argument): bool => str_starts_with($argument, '--case='));
$fixtureCase = $caseArgument ? substr($caseArgument, strlen('--case=')) : 'short';
$official = in_array('--official', $arguments, true);
$output = collect($arguments)->first(fn (string $argument): bool => ! str_starts_with($argument, '--'))
    ?? $root.'/tmp/pdfs/document-template-step11/html/'.$fixtureCase.'.html';
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0777, true);
}

$columns = [
    ['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'width' => 37],
    ['key' => 'rate_20', 'label' => "20'", 'group' => 'Full Container (Dry)', 'value_type' => 'currency', 'width' => 19],
    ['key' => 'rate_40', 'label' => "40'", 'group' => 'Full Container (Dry)', 'value_type' => 'currency', 'width' => 19],
    ['key' => 'note', 'label' => 'Note', 'value_type' => 'text', 'width' => 15],
];
$rawTemplate = <<<'HTML'
<table style="width: 100%; border-collapse: collapse">
<tbody><tr><td><h2>{{ company_legal_name }}</h2><p>{{ company_address }}</p><p>{{ company_email }}</p></td><td style="text-align: right"><div>{{ company_logo }}</div></td></tr></tbody>
</table>
<hr>
<table style="width: 100%; border-collapse: collapse">
<tbody>
<tr><td><strong>No.:</strong> {{ quotation_number }}</td><td><strong>On:</strong> {{ quotation_date }}</td></tr>
<tr><td><strong>To:</strong> {{ customer_name }}</td><td><strong>By:</strong> {{ sender_name }}<br>{{ sender_title }}</td></tr>
<tr><td><strong>At:</strong> {{ customer_address }}</td><td><strong>Re:</strong> {{ subject }}</td></tr>
</tbody>
</table>
<p>{{ intro_text }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<p>{{ closing_text }}</p>
<div>{{ signature_block }}</div>
<div>{{ draft_watermark }}</div>
HTML;
$content = app(DocumentTemplateHtmlSanitizer::class)->sanitize($rawTemplate);
$schema = ['columns' => $columns, 'presentation' => ['type' => 'table']];
$snapshot = [
    'template_id' => (string) Str::uuid(),
    'template_key' => 'step-11-visual-proof',
    'template_version' => 1,
    'content_html' => $content,
    'item_schema' => $schema,
    'company_profile' => [
        'legal_name' => 'PT Jayabaru Logistik Utama',
        'display_name' => 'Jayabaru Logistik Utama',
        'address_lines' => ['Jl. Menteng Metropolitan (MM) Blok D7/27', 'Ujung Menteng, Cakung'],
        'city' => 'Jakarta Timur', 'postal_code' => '13960', 'country' => 'Indonesia',
        'email' => 'office@jayabaru-logistics.com', 'phone' => '+62 21 0000 0000',
        'primary_color' => '#087eae',
    ],
];

$itemCount = match ($fixtureCase) {
    'table-large' => 42,
    'multi-page' => 78,
    default => 2,
};
$quotation = new Quotation([
    'quotation_date' => CarbonImmutable::parse('2026-07-23'),
    'subject' => match ($fixtureCase) {
        'long-text' => 'Integrated Container Storage, Handling, Inspection, Reporting, and Supporting Operational Services',
        default => 'Quotation for Container Storage',
    },
    'customer_name' => 'PT Example Logistics Indonesia',
    'customer_address' => $fixtureCase === 'long-text'
        ? "Gedung Example Logistics Lantai 12\nJl. Jenderal Sudirman Kav. 45-46\nJakarta Selatan 12930"
        : 'Jakarta',
    'attention_name' => 'Vina Louise', 'attention_role' => 'FE Manager',
    'sender_name' => 'Ardhian Widyanto', 'sender_title' => 'Commercial Manager',
    'currency' => 'IDR',
    'intro_text' => $fixtureCase === 'long-text'
        ? str_repeat('We are pleased to submit a detailed commercial proposal covering integrated operational requirements, traceable documentation, and coordinated service delivery. ', 4)
        : 'We are pleased to submit our official quotation proposal as follows:',
    'closing_text' => $fixtureCase === 'long-text'
        ? str_repeat('We trust this proposal addresses the operational requirements and look forward to discussing the implementation schedule. ', 3)
        : 'We hope the proposal meets your requirements and look forward to receiving your order.',
    'item_schema' => $schema,
    'template_snapshot' => $snapshot,
    'template_content_sha256' => hash('sha256', $content),
    'placeholder_contract_version' => 1,
]);
$quotation->setRelation('document', $official ? new Document(['number' => 'QT-JBLU-2026070001']) : null);

$items = [];
for ($position = 1; $position <= $itemCount; $position++) {
    $item = new QuotationItem(['id' => (string) Str::uuid(), 'position' => $position]);
    $description = match ($fixtureCase) {
        'long-text' => $position === 1
            ? str_repeat('Integrated container storage, handling, inspection, and reporting service with traceable operational documentation. ', 5)
            : 'Storage and handling service per day',
        'multi-page' => "Container service line {$position} with operational coordination and documented handover",
        default => $position === 1 ? 'Lift on / lift off service' : "Storage service line {$position}",
    };
    $values = [
        'description' => $description,
        'rate_20' => (string) (25000 + $position * 1250),
        'rate_40' => (string) (45000 + $position * 1500),
        'note' => $position % 3 === 0 ? "Operational note {$position}" : '',
    ];
    $item->setRelation('values', new Collection(array_map(
        fn (array $column, int $valuePosition): QuotationItemValue => new QuotationItemValue([
            'key' => $column['key'], 'value' => $values[$column['key']],
            'value_type' => $column['value_type'], 'position' => $valuePosition + 1,
        ]),
        $columns,
        array_keys($columns),
    )));
    $items[] = $item;
}
$quotation->setRelation('items', new Collection($items));
$terms = $fixtureCase === 'multi-page'
    ? array_map(fn (int $position): QuotationTerm => new QuotationTerm(['position' => $position, 'content' => "Term {$position}: conditions remain subject to written confirmation."]), range(1, 10))
    : [
        new QuotationTerm(['position' => 1, 'content' => 'TOP: H-1 before unit release.']),
        new QuotationTerm(['position' => 2, 'content' => 'VAT is excluded.']),
    ];
$quotation->setRelation('terms', new Collection($terms));

$logo = 'data:image/png;base64,'.base64_encode(file_get_contents($root.'/public/static/jblu.png'));
$renderedHtml = app(QuotationTemplateRenderer::class)->render($quotation, ! $official, $logo);
$html = View::make('quotations.document', [
    'quotation' => $quotation,
    'renderedHtml' => $renderedHtml,
    'isDraft' => ! $official,
    'browserPreview' => false,
])->render();

file_put_contents($output, $html);
echo realpath($output);
