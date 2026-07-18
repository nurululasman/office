<?php

namespace App\Services\Quotations;

use App\Jobs\GenerateQuotationPdf;
use App\Models\GeneratedFile;
use App\Models\Quotation;
use App\Models\User;

final class QuotationPdfDispatcher
{
    public function dispatch(Quotation $quotation, User $actor): GeneratedFile
    {
        $path = "quotations/{$quotation->getKey()}/quotation.pdf";
        $file = GeneratedFile::query()->firstOrCreate(
            ['owner_type' => $quotation->getMorphClass(), 'owner_id' => $quotation->getKey(), 'kind' => 'quotation_pdf', 'path' => $path],
            [
                'template_id' => $quotation->template_id, 'status' => 'queued', 'attempts' => 0,
                'disk' => config('office.documents.disk'), 'mime_type' => 'application/pdf',
                'size' => null, 'sha256' => null, 'queued_at' => now('UTC'),
                'generated_at' => null, 'generated_by' => $actor->getKey(),
            ],
        );

        if ($file->wasRecentlyCreated || $file->status === 'failed') {
            if ($file->status === 'failed') {
                $file->update(['status' => 'queued', 'last_error' => null, 'queued_at' => now('UTC')]);
            }
            GenerateQuotationPdf::dispatch($file->getKey())
                ->onConnection(config('office.queues.pdf_connection'))
                ->onQueue(config('office.queues.pdf'));
        }

        return $file;
    }
}
