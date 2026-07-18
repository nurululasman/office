<?php

declare(strict_types=1);

use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $documentTypeId, $userId, $title, $purpose] = $argv;
$sourceId = $argv[5] ?? null;

try {
    $documentType = DocumentType::query()->findOrFail($documentTypeId);
    $user = User::query()->findOrFail($userId);
    $source = $sourceId !== null ? DocumentType::query()->findOrFail($sourceId) : null;
    $document = $app->make(DocumentNumberIssuer::class)->issue(
        $documentType,
        $user,
        $title,
        $purpose,
        $source,
    );

    echo json_encode([
        'id' => $document->getKey(),
        'number' => $document->number,
        'sequence_value' => $document->sequence_value,
        'document_type_id' => $document->document_type_id,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
