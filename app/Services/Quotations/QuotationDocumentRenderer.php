<?php

namespace App\Services\Quotations;

use App\Models\Quotation;
use App\Services\DocumentTemplates\QuotationTemplateRenderer;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class QuotationDocumentRenderer
{
    public function __construct(
        private readonly QuotationTemplateRenderer $templateRenderer,
    ) {}

    public function content(
        Quotation $quotation,
        bool $isDraft,
        bool $requireActivationContract = true,
    ): string {
        return $this->templateRenderer->render(
            $quotation,
            $isDraft,
            $this->logoDataUri($quotation),
            $requireActivationContract,
        );
    }

    private function logoDataUri(Quotation $quotation): ?string
    {
        $company = is_array($quotation->template_snapshot['company_profile'] ?? null)
            ? $quotation->template_snapshot['company_profile']
            : [];
        $configured = is_string($company['logo_path'] ?? null)
            ? public_path(ltrim($company['logo_path'], '/\\'))
            : null;
        $path = $configured && File::isFile($configured)
            ? $configured
            : public_path('static/jblu.png');

        if (! File::isFile($path)) {
            return null;
        }

        $contents = File::get($path);
        $expectedHash = $company['logo_sha256'] ?? null;
        if (is_string($expectedHash) && $expectedHash !== '' && ! hash_equals(strtolower($expectedHash), hash('sha256', $contents))) {
            throw new RuntimeException('Checksum logo snapshot quotation tidak cocok.');
        }

        $mime = File::mimeType($path);
        if (! is_string($mime) || ! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw new RuntimeException('Format logo snapshot quotation tidak didukung.');
        }

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
