<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('customer_address')->nullable()->change();
            $table->string('sender_title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('customer_address')->nullable(false)->change();
            $table->string('sender_title')->nullable(false)->change();
        });
    }
};
