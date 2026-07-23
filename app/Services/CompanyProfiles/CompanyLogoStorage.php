<?php

namespace App\Services\CompanyProfiles;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CompanyLogoStorage
{
    /** @return array{logo_path: string, logo_sha256: string} */
    public function store(UploadedFile $logo): array
    {
        $contents = file_get_contents($logo->getRealPath());
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('File logo tidak dapat dibaca.');
        }

        $sha256 = hash('sha256', $contents);
        $extension = $logo->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $path = "company-logos/{$sha256}.{$extension}";
        Storage::disk('public')->put($path, $contents);

        return [
            'logo_path' => '/storage/'.$path,
            'logo_sha256' => $sha256,
        ];
    }
}
