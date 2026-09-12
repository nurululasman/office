<?php

namespace App\Services\Quotations;

use App\Models\Quotation;
use App\Services\DocumentTemplates\QuotationTemplateRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
        $showSenderSignatureAndStamp = $quotation->isSelfSender()
            || $quotation->status === 'complete'
            || $quotation->approved_at !== null;

        $stampSource = $showSenderSignatureAndStamp ? $this->stampDataUri($quotation) : null;
        $signatureSource = $showSenderSignatureAndStamp ? $this->signatureDataUri($quotation) : null;

        return $this->templateRenderer->render(
            $quotation,
            $isDraft,
            $this->logoDataUri($quotation),
            $requireActivationContract,
            $stampSource,
            $signatureSource,
            $showSenderSignatureAndStamp,
        );
    }

    private function stampDataUri(Quotation $quotation): ?string
    {
        $company = is_array($quotation->template_snapshot['company_profile'] ?? null)
            ? $quotation->template_snapshot['company_profile']
            : [];
        $stampPath = $company['stamp_path'] ?? null;
        $stampHash = $company['stamp_sha256'] ?? null;

        if (! is_string($stampPath) || $stampPath === '') {
            $liveProfile = $quotation->template?->companyProfile;
            $stampPath = $liveProfile?->stamp_path;
            $stampHash = $liveProfile?->stamp_sha256;
        }

        if (! is_string($stampPath) || $stampPath === '') {
            return null;
        }

        return $this->imageDataUri($stampPath, $stampHash, 'stamp');
    }

    private function signatureDataUri(Quotation $quotation): ?string
    {
        $quotation->loadMissing(['sender', 'creator']);
        $sender = $quotation->sender ?: $quotation->creator;
        if (! $sender || ! is_string($sender->signature_path) || $sender->signature_path === '') {
            return null;
        }

        return $this->imageDataUri($sender->signature_path, $sender->signature_sha256, 'tanda tangan');
    }

    private function logoDataUri(Quotation $quotation): ?string
    {
        $company = is_array($quotation->template_snapshot['company_profile'] ?? null)
            ? $quotation->template_snapshot['company_profile']
            : [];
        $configured = is_string($company['logo_path'] ?? null) && $company['logo_path'] !== ''
            ? $company['logo_path']
            : null;

        if ($configured !== null) {
            $dataUri = $this->imageDataUri($configured, $company['logo_sha256'] ?? null, 'logo');
            if ($dataUri !== null) {
                return $dataUri;
            }
        }

        $fallbackPath = public_path('static/jblu.png');
        if (! File::isFile($fallbackPath)) {
            return null;
        }

        $contents = File::get($fallbackPath);
        $mime = File::mimeType($fallbackPath);

        return "data:{$mime};base64,".base64_encode($contents);
    }

    private function imageDataUri(string $uriPath, ?string $expectedHash, string $typeLabel): ?string
    {
        $contents = null;
        $mime = null;

        $diskRelative = str_starts_with($uriPath, '/storage/')
            ? substr($uriPath, strlen('/storage/'))
            : null;

        if ($diskRelative !== null && Storage::disk('public')->exists($diskRelative)) {
            $contents = Storage::disk('public')->get($diskRelative);
            $mime = Storage::disk('public')->mimeType($diskRelative);
        } else {
            $realPath = public_path(ltrim($uriPath, '/\\'));
            if (File::isFile($realPath)) {
                $contents = File::get($realPath);
                $mime = File::mimeType($realPath);
            }
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        if (is_string($expectedHash) && $expectedHash !== '' && ! hash_equals(strtolower($expectedHash), hash('sha256', $contents))) {
            throw new RuntimeException("Checksum {$typeLabel} quotation tidak cocok.");
        }

        if (! is_string($mime) || ! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $ext = strtolower(pathinfo($uriPath, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        }

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
