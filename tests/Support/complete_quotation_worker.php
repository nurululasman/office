<?php

declare(strict_types=1);

use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\QuotationWorkflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $quotationId, $userId, $lockVersion, $documentTypeCode] = $argv;

try {
    config()->set('office.quotation_document_type_code', $documentTypeCode);
    $quotation = Quotation::query()->findOrFail($quotationId);
    $user = User::query()->findOrFail((int) $userId);
    $request = Request::create('/concurrency/complete', 'POST', ['lock_version' => (int) $lockVersion]);
    $result = $app->make(QuotationWorkflow::class)->completeDirect($quotation, $user, $request);

    echo json_encode([
        'quotation_id' => $result->getKey(),
        'document_id' => $result->document_id,
        'number' => $result->document()->value('number'),
        'status' => $result->status,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
