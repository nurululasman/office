<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignUuid('parent_item_id')
                ->nullable()
                ->after('quotation_id')
                ->constrained('quotation_items')
                ->cascadeOnDelete();
            $table->index(['quotation_id', 'parent_item_id'], 'quotation_items_parent_index');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropIndex('quotation_items_parent_index');
            $table->dropConstrainedForeignId('parent_item_id');
        });
    }
};
