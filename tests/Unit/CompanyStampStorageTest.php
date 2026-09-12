<?php

namespace Tests\Unit;

use App\Services\CompanyProfiles\CompanyStampStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CompanyStampStorageTest extends TestCase
{
    public function test_it_stores_png_stamp_with_sha256_filename_on_public_disk(): void
    {
        Storage::fake('public');
        $storage = new CompanyStampStorage();

        $file = UploadedFile::fake()->image('stamp.png', 100, 100);
        $result = $storage->store($file);

        $expectedHash = hash('sha256', file_get_contents($file->getRealPath()));
        $this->assertSame($expectedHash, $result['stamp_sha256']);
        $this->assertSame("/storage/company-stamps/{$expectedHash}.png", $result['stamp_path']);
        Storage::disk('public')->assertExists("company-stamps/{$expectedHash}.png");
    }

    public function test_it_stores_jpeg_stamp_with_jpg_extension(): void
    {
        Storage::fake('public');
        $storage = new CompanyStampStorage();

        $file = UploadedFile::fake()->image('stamp.jpg', 100, 100);
        $result = $storage->store($file);

        $expectedHash = hash('sha256', file_get_contents($file->getRealPath()));
        $this->assertSame($expectedHash, $result['stamp_sha256']);
        $this->assertSame("/storage/company-stamps/{$expectedHash}.jpg", $result['stamp_path']);
        Storage::disk('public')->assertExists("company-stamps/{$expectedHash}.jpg");
    }

    public function test_it_throws_exception_if_file_is_empty(): void
    {
        $storage = new CompanyStampStorage();
        $file = UploadedFile::fake()->create('empty.png', 0, 'image/png');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File stamp tidak dapat dibaca.');

        $storage->store($file);
    }
}
