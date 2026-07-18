<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeAuditSubjectsUuidCompatible();

        Schema::create('document_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('number_pattern', 255);
            $table->string('reset_period', 20)->default('yearly');
            $table->string('approval_mode', 20)->default('direct');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestampsTz();

            $table->unique(['document_type_id', 'period_year'], 'document_sequences_type_year_unique');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sequence_value');
            $table->unsignedSmallInteger('period_year');
            $table->string('number', 255);
            $table->string('title', 255);
            $table->text('purpose');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->timestampTz('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['document_type_id', 'period_year', 'sequence_value'],
                'documents_type_year_sequence_unique'
            );
            $table->unique(
                ['document_type_id', 'period_year', 'number'],
                'documents_type_year_number_unique'
            );
            $table->unique(['source_type', 'source_id'], 'documents_source_unique');
            $table->index(['document_type_id', 'period_year', 'issued_at'], 'documents_register_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_types ADD CONSTRAINT document_types_reset_period_check CHECK (reset_period = 'yearly')");
            DB::statement("ALTER TABLE document_types ADD CONSTRAINT document_types_approval_mode_check CHECK (approval_mode IN ('direct', 'maker_checker'))");
            DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_period_year_check CHECK (period_year BETWEEN 1 AND 9999)');
            DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_last_value_check CHECK (last_value >= 0)');
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_period_year_check CHECK (period_year BETWEEN 1 AND 9999)');
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_sequence_value_check CHECK (sequence_value > 0)');
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_source_pair_check CHECK ((source_type IS NULL) = (source_id IS NULL))');
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_void_pair_check CHECK ((voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND length(trim(void_reason)) > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('document_types');
    }

    private function makeAuditSubjectsUuidCompatible(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $dataType = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'audit_logs')
            ->where('column_name', 'subject_id')
            ->value('data_type');

        if ($dataType === 'bigint') {
            DB::statement('DROP INDEX IF EXISTS audit_logs_subject_type_subject_id_index');
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN subject_id TYPE varchar(255) USING subject_id::varchar');
            DB::statement('CREATE INDEX audit_logs_subject_type_subject_id_index ON audit_logs (subject_type, subject_id)');
        }
    }
};
