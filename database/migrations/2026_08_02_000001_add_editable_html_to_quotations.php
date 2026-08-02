<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('content_html')->nullable()->after('closing_text');
            $table->char('content_sha256', 64)->nullable()->after('content_html');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn(['content_html', 'content_sha256']);
        });
    }
};
