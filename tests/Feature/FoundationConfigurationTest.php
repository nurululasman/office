<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationConfigurationTest extends TestCase
{
    public function test_office_foundation_uses_safe_runtime_defaults(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', config('office.business_timezone'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame('default', config('office.queues.default'));
        $this->assertSame('pdf', config('office.queues.pdf'));
        $this->assertNotSame(config('office.queues.default'), config('office.queues.pdf'));
    }

    public function test_document_output_uses_a_non_public_local_disk(): void
    {
        $disk = config('office.documents.disk');

        $this->assertSame('documents', $disk);
        $this->assertSame('local', config("filesystems.disks.{$disk}.driver"));
        $this->assertFalse(config("filesystems.disks.{$disk}.serve"));
        $this->assertStringStartsWith(
            storage_path('app/private'),
            config("filesystems.disks.{$disk}.root"),
        );
    }

    public function test_pdf_jobs_use_database_connection_and_dedicated_queue(): void
    {
        $this->assertSame('database', config('office.queues.pdf_connection'));
        $this->assertSame('pdf', config('office.queues.pdf'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }
}
