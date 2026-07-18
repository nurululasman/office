<?php

namespace App\Jobs;

use App\Models\GeneratedFile;
use App\Services\Quotations\QuotationPdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateQuotationPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 150;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public readonly string $generatedFileId)
    {
        $this->afterCommit();
    }

    public function handle(QuotationPdfRenderer $renderer): void
    {
        $file = GeneratedFile::query()->findOrFail($this->generatedFileId);
        if ($file->status === 'ready') {
            return;
        }

        $file->update([
            'status' => 'processing', 'attempts' => $file->attempts + 1,
            'started_at' => now('UTC'), 'last_error' => null,
        ]);

        try {
            $renderer->render($file->refresh());
        } catch (Throwable $exception) {
            $file->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 5000)]);
            throw $exception;
        }
    }
}
