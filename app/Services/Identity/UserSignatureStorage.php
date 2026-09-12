<?php

namespace App\Services\Identity;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class UserSignatureStorage
{
    /** @return array{signature_path: string, signature_sha256: string} */
    public function store(UploadedFile $signature): array
    {
        $contents = file_get_contents($signature->getRealPath());
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('File tanda tangan tidak dapat dibaca.');
        }

        $sha256 = hash('sha256', $contents);
        $extension = $signature->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $path = "user-signatures/{$sha256}.{$extension}";
        Storage::disk('public')->put($path, $contents);

        return [
            'signature_path' => '/storage/'.$path,
            'signature_sha256' => $sha256,
        ];
    }
}
