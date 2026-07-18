<?php

namespace App\Services\Quotations;

use App\Models\GeneratedFile;
use App\Models\Quotation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class QuotationPdfRenderer
{
    public function __construct(
        private readonly QuotationValueFormatter $formatter,
        private readonly QuotationTableLayout $tableLayout,
    ) {}

    public function render(GeneratedFile $file): void
    {
        $quotation = Quotation::query()->with([
            'document', 'template.companyProfile', 'items.values', 'terms',
        ])->findOrFail($file->owner_id);

        if ($file->owner_type !== $quotation->getMorphClass() || $quotation->status !== 'complete' || $quotation->document_id === null) {
            throw new RuntimeException('PDF resmi hanya dapat dibuat dari snapshot quotation complete yang telah bernomor.');
        }

        $logo = File::get(public_path('static/jblu.png'));
        $html = view('quotations.document', [
            'quotation' => $quotation,
            'formatter' => $this->formatter,
            'tableLayout' => $this->tableLayout->build($quotation->item_schema),
            'isDraft' => false,
            'browserPreview' => false,
            'logoSource' => 'data:image/png;base64,'.base64_encode($logo),
        ])->render();

        $temporaryDirectory = storage_path('app/private/tmp/pdf-'.Str::uuid());
        File::ensureDirectoryExists($temporaryDirectory);
        $htmlPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'quotation.html';
        $pdfPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'quotation.pdf';

        try {
            File::put($htmlPath, $html);
            $process = new Process([
                $this->chromeBinary(), '--headless=new', '--disable-gpu', '--no-sandbox',
                '--disable-dev-shm-usage', '--no-pdf-header-footer', '--user-data-dir='.$temporaryDirectory.DIRECTORY_SEPARATOR.'chrome-profile',
                '--print-to-pdf='.$pdfPath,
                'file:///'.str_replace('\\', '/', $htmlPath),
            ]);
            $process->setTimeout((float) config('office.pdf.timeout_seconds', 120));
            $process->mustRun();

            if (! File::exists($pdfPath) || File::size($pdfPath) === 0) {
                throw new RuntimeException('Chrome tidak menghasilkan file PDF yang valid.');
            }

            $contents = File::get($pdfPath);
            $disk = Storage::disk($file->disk);
            $temporaryPath = $file->path.'.tmp-'.Str::uuid();
            $disk->put($temporaryPath, $contents);
            if ($disk->exists($file->path)) {
                $disk->delete($file->path);
            }
            $disk->move($temporaryPath, $file->path);

            $file->update([
                'status' => 'ready', 'mime_type' => 'application/pdf', 'size' => strlen($contents),
                'sha256' => hash('sha256', $contents), 'last_error' => null, 'generated_at' => now('UTC'),
            ]);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function chromeBinary(): string
    {
        $configured = config('office.pdf.chrome_binary');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Chrome binary tidak ditemukan. Isi OFFICE_CHROME_BINARY dengan path executable Chrome/Chromium.');
    }
}
