<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->text('bank_information')->nullable()->after('tax_id');
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->uuid('document_type_id')->nullable()->after('company_profile_id');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_templates ADD CONSTRAINT document_templates_document_type_id_foreign FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE RESTRICT');
        }

        $defaultTypeId = DB::table('document_types')
            ->where('code', (string) config('office.quotation_document_type_code', 'QUOTATION'))
            ->value('id');

        if ($defaultTypeId !== null) {
            DB::table('document_templates')->where('type', 'quotation')
                ->whereNull('document_type_id')->update(['document_type_id' => $defaultTypeId]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_document_type_id_foreign');
        }
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('document_type_id');
        });
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('bank_information');
        });
    }
};
