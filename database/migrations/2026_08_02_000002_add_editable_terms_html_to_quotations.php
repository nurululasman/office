<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('terms_html')->nullable()->after('content_sha256');
            $table->char('terms_sha256', 64)->nullable()->after('terms_html');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn(['terms_html', 'terms_sha256']);
        });
    }
};
