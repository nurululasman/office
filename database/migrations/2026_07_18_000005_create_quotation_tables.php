<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_code', 50)->unique();
            $table->string('legal_name', 255);
            $table->string('display_name', 255);
            $table->json('address_lines');
            $table->string('city', 150);
            $table->string('postal_code', 20);
            $table->string('country', 2);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->char('logo_sha256', 64)->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_profile_id')->constrained()->restrictOnDelete();
            $table->string('type', 50);
            $table->unsignedInteger('version');
            $table->string('name', 255);
            $table->json('settings');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['type', 'version'], 'document_templates_type_version_unique');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('template_id')->constrained('document_templates')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('approval_mode', 20);
            $table->date('quotation_date');
            $table->string('subject');
            $table->string('customer_name');
            $table->text('customer_address');
            $table->string('attention_name')->nullable();
            $table->string('attention_role')->nullable();
            $table->string('sender_name');
            $table->string('sender_title');
            $table->json('item_schema');
            $table->char('currency', 3)->default('IDR');
            $table->text('intro_text')->nullable();
            $table->text('closing_text')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('approval_bypassed_at')->nullable();
            $table->foreignId('approval_bypassed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('approval_bypass_reason')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'quotation_date']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestampsTz();

            $table->unique(['quotation_id', 'position']);
        });

        Schema::create('quotation_item_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_item_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('value_type', 20);
            $table->unsignedInteger('position');
            $table->timestampsTz();

            $table->unique(['quotation_item_id', 'key']);
            $table->unique(['quotation_item_id', 'position']);
        });

        Schema::create('quotation_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->timestampsTz();

            $table->unique(['quotation_id', 'position']);
        });

        Schema::create('generated_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type');
            $table->uuid('owner_id');
            $table->foreignUuid('template_id')->constrained('document_templates')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('disk', 100);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestampTz('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
            $table->unique(['owner_type', 'owner_id', 'kind', 'path'], 'generated_files_owner_kind_path_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_check CHECK (status IN ('draft', 'pending_approval', 'rejected', 'complete', 'void'))");
            DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_approval_mode_check CHECK (approval_mode IN ('direct', 'maker_checker'))");
            DB::statement("ALTER TABLE quotation_item_values ADD CONSTRAINT quotation_item_values_key_check CHECK (key ~ '^[a-z][a-z0-9_]*$')");
            DB::statement("ALTER TABLE quotation_item_values ADD CONSTRAINT quotation_item_values_type_check CHECK (value_type IN ('text', 'decimal', 'integer', 'date', 'boolean', 'currency'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_files');
        Schema::dropIfExists('quotation_terms');
        Schema::dropIfExists('quotation_item_values');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('company_profiles');
    }
};
