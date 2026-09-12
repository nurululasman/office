<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('stamp_path')->nullable()->after('logo_sha256');
            $table->char('stamp_sha256', 64)->nullable()->after('stamp_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['stamp_path', 'stamp_sha256']);
        });
    }
};
