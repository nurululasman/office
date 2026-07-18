<?php

declare(strict_types=1);

use App\Services\Quotations\QuotationTableLayout;
use App\Services\Quotations\QuotationValueFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Fluent;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$longFixture = in_array('--long', $arguments, true);
$caseArgument = collect($arguments)->first(fn (string $argument): bool => str_starts_with($argument, '--case='));
$fixtureCase = $caseArgument ? substr($caseArgument, strlen('--case=')) : ($longFixture ? 'many-items' : 'short');
$official = in_array('--official', $arguments, true);
$output = collect($arguments)->first(fn (string $argument): bool => ! str_starts_with($argument, '--'))
    ?? $root.'/tmp/pdfs/off0401-result/off0401-template.html';
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0777, true);
}

$profile = new Fluent([
    'legal_name' => 'PT. Jayabaru Logistik Utama', 'display_name' => 'Jayabaru Logistik Utama',
    'address_lines' => ['Jl. Menteng Metropolitan (MM) Blok D7/27', 'Ujung Menteng Cakung Jakarta Timur'],
    'city' => 'Jakarta', 'postal_code' => '13960', 'country' => 'Indonesia',
    'email' => 'ardhian.widyanto@jayabaru-logistics.com',
]);
$template = new Fluent(['companyProfile' => $profile]);
$columns = [
    ['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'width' => 27],
    ['key' => 'rate_20', 'label' => "20'", 'group' => 'Full Container (Dry)', 'value_type' => 'currency', 'width' => 19],
    ['key' => 'rate_40', 'label' => "40'", 'group' => 'Full Container (Dry)', 'value_type' => 'currency', 'width' => 19],
    ['key' => 'note', 'label' => 'Note', 'value_type' => 'text', 'width' => 25],
];
$makeItem = static function (int $position, array $values) use ($columns): Fluent {
    return new Fluent(['position' => $position, 'values' => new Collection(array_map(
        fn (array $column): Fluent => new Fluent(['key' => $column['key'], 'value' => $values[$column['key']] ?? null, 'value_type' => $column['value_type']]),
        $columns,
    ))]);
};
$manyItems = static fn (int $count): Collection => new Collection(array_map(fn (int $position): Fluent => $makeItem($position, [
    'description' => $position % 5 === 0 ? "Storage handling service line {$position} with a deliberately longer description to verify wrapping without splitting the row across pages." : "Container service line {$position}",
    'rate_20' => (string) (25000 + $position * 1250), 'rate_40' => (string) (45000 + $position * 1500),
    'note' => $position % 3 === 0 ? "Operational note {$position}" : null,
]), range(1, $count)));
$items = match ($fixtureCase) {
    'long-text' => new Collection([
        $makeItem(1, ['description' => str_repeat('Integrated container storage, handling, inspection, and reporting service with traceable operational documentation. ', 3), 'rate_20' => '295000', 'rate_40' => '335000', 'note' => str_repeat('Subject to written operational confirmation. ', 2)]),
        $makeItem(2, ['description' => 'Storage / day', 'rate_20' => '35000', 'rate_40' => '45000']),
    ]),
    'special' => new Collection([
        $makeItem(1, ['description' => "Café naïve & fragile cargo <priority> / 20' and 40\"", 'rate_20' => '125000.50', 'rate_40' => '-2500', 'note' => 'A&B: 50% (uji #1); email qa+jblu@example.test']),
        $makeItem(2, ['description' => 'Unicode: é, ñ, ü; symbols: + = % @ #', 'rate_20' => '9007199254740993.25', 'rate_40' => '0', 'note' => 'Quoted "text" & customer\'s apostrophe']),
    ]),
    'empty' => new Collection([
        $makeItem(1, ['description' => 'Only description is populated']),
        $makeItem(2, ['rate_20' => '0', 'note' => 'Rate 40 intentionally empty']),
        $makeItem(3, []),
    ]),
    'many-items' => $manyItems(75),
    default => new Collection([$makeItem(1, ['description' => 'LOLO', 'rate_20' => '295000', 'rate_40' => '335000']), $makeItem(2, ['description' => 'Storage / day', 'rate_20' => '35000', 'rate_40' => '45000', 'note' => 'Free storage 3 days'])]),
};
$terms = $fixtureCase === 'many-items'
    ? new Collection(array_map(fn (int $position): Fluent => new Fluent(['content' => "Term {$position}: operational conditions remain subject to written confirmation."]), range(1, 8)))
    : new Collection([new Fluent(['content' => $fixtureCase === 'special' ? 'Terms: A&B, 20\', 40", café; <escaped safely>.' : 'TOP: H-1 before unit release.']), new Fluent(['content' => 'Exclude VAT'])]);
$document = $official ? new Fluent(['number' => 'QT-JBLU-2026070001']) : null;
$quotation = new Fluent([
    'subject' => 'Quotation for Container Storage', 'quotation_date' => CarbonImmutable::parse('2025-08-28'),
    'customer_name' => 'PT EPS Logisitc', 'attention_name' => 'Ms. Vina Louise', 'attention_role' => 'FE Manager',
    'sender_name' => 'Ardhian Widyanto', 'sender_title' => 'Commercial Manager', 'currency' => 'IDR',
    'intro_text' => 'We are pleased to submit our official quotation proposal as follows:',
    'closing_text' => 'Hope the above can meet your requirements. Looking forward to receiving your valuable order.',
    'template' => $template, 'document' => $document, 'item_schema' => ['columns' => $columns],
    'items' => $items, 'terms' => $terms,
]);
$logo = 'data:image/png;base64,'.base64_encode(file_get_contents($root.'/public/static/jblu.png'));
$html = View::make('quotations.document', [
    'quotation' => $quotation, 'formatter' => app(QuotationValueFormatter::class),
    'tableLayout' => app(QuotationTableLayout::class)->build($quotation->item_schema),
    'isDraft' => ! $official, 'browserPreview' => false, 'logoSource' => $logo,
])->render();
file_put_contents($output, $html);
echo realpath($output);
