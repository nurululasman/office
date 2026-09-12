<?php

namespace App\Services\CompanyProfiles;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CompanyStampStorage
{
    /** @return array{stamp_path: string, stamp_sha256: string} */
    public function store(UploadedFile $stamp): array
    {
        $contents = file_get_contents($stamp->getRealPath());
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('File stamp tidak dapat dibaca.');
        }

        $sha256 = hash('sha256', $contents);
        $extension = $stamp->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $path = "company-stamps/{$sha256}.{$extension}";
        Storage::disk('public')->put($path, $contents);

        return [
            'stamp_path' => '/storage/'.$path,
            'stamp_sha256' => $sha256,
        ];
    }
}
