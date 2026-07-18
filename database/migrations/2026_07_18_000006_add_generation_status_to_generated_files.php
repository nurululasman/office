<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_files', function (Blueprint $table) {
            $table->unsignedBigInteger('size')->nullable()->change();
            $table->char('sha256', 64)->nullable()->change();
            $table->timestampTz('generated_at')->nullable()->change();
            $table->string('status', 20)->default('queued')->after('kind')->index();
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->text('last_error')->nullable()->after('sha256');
            $table->timestampTz('queued_at')->nullable()->after('last_error');
            $table->timestampTz('started_at')->nullable()->after('queued_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE generated_files ADD CONSTRAINT generated_files_status_check CHECK (status IN ('queued', 'processing', 'ready', 'failed'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE generated_files DROP CONSTRAINT IF EXISTS generated_files_status_check');
        }

        Schema::table('generated_files', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'attempts', 'last_error', 'queued_at', 'started_at']);
        });

        DB::table('generated_files')->whereNull('size')->update(['size' => 0]);
        DB::table('generated_files')->whereNull('sha256')->update(['sha256' => str_repeat('0', 64)]);
        DB::table('generated_files')->whereNull('generated_at')->update(['generated_at' => now('UTC')]);

        Schema::table('generated_files', function (Blueprint $table) {
            $table->unsignedBigInteger('size')->nullable(false)->change();
            $table->char('sha256', 64)->nullable(false)->change();
            $table->timestampTz('generated_at')->nullable(false)->change();
        });
    }
};
