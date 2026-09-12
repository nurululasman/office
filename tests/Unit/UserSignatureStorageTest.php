<?php

namespace Tests\Unit;

use App\Services\Identity\UserSignatureStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class UserSignatureStorageTest extends TestCase
{
    public function test_it_stores_png_signature_with_sha256_filename_on_public_disk(): void
    {
        Storage::fake('public');
        $storage = new UserSignatureStorage();

        $file = UploadedFile::fake()->image('signature.png', 120, 60);
        $result = $storage->store($file);

        $expectedHash = hash('sha256', file_get_contents($file->getRealPath()));
        $this->assertSame($expectedHash, $result['signature_sha256']);
        $this->assertSame("/storage/user-signatures/{$expectedHash}.png", $result['signature_path']);
        Storage::disk('public')->assertExists("user-signatures/{$expectedHash}.png");
    }

    public function test_it_stores_jpeg_signature_with_jpg_extension(): void
    {
        Storage::fake('public');
        $storage = new UserSignatureStorage();

        $file = UploadedFile::fake()->image('signature.jpg', 120, 60);
        $result = $storage->store($file);

        $expectedHash = hash('sha256', file_get_contents($file->getRealPath()));
        $this->assertSame($expectedHash, $result['signature_sha256']);
        $this->assertSame("/storage/user-signatures/{$expectedHash}.jpg", $result['signature_path']);
        Storage::disk('public')->assertExists("user-signatures/{$expectedHash}.jpg");
    }

    public function test_it_throws_exception_if_file_is_empty(): void
    {
        $storage = new UserSignatureStorage();
        $file = UploadedFile::fake()->create('empty.png', 0, 'image/png');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File tanda tangan tidak dapat dibaca.');

        $storage->store($file);
    }
}
