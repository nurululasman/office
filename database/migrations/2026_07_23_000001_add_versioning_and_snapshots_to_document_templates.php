<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_CONTENT = <<<'HTML'
<p>{{ company_display_name }}</p>
<p>{{ quotation_number }}</p>
<p>{{ quotation_date }}</p>
<p>{{ customer_name }}</p>
<p>{{ customer_address }}</p>
<p>{{ subject }}</p>
<p>{{ intro_text }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<p>{{ closing_text }}</p>
<p>{{ sender_name }}</p>
<p>{{ sender_title }}</p>
HTML;

    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('template_key', 100)->nullable()->after('type');
            $table->string('status', 20)->default('active')->after('name');
            $table->longText('content_html')->nullable()->after('status');
            $table->char('content_sha256', 64)->nullable()->after('content_html');
            $table->json('item_schema')->nullable()->after('settings');
            $table->text('default_intro_text')->nullable()->after('item_schema');
            $table->text('default_closing_text')->nullable()->after('default_intro_text');
            $table->json('default_terms')->nullable()->after('default_closing_text');
            $table->json('editor_config')->nullable()->after('default_terms');
            $table->unsignedInteger('lock_version')->default(0)->after('editor_config');
            $table->foreignId('created_by')->nullable()->after('lock_version')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->after('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('activated_at')->nullable()->after('activated_by');
        });

        $activeVersions = DB::table('document_templates')
            ->where('is_active', true)
            ->select('type', DB::raw('MAX(version) AS active_version'))
            ->groupBy('type')
            ->pluck('active_version', 'type');

        DB::table('document_templates')->orderBy('type')->orderBy('version')->get()->each(function (object $template) use ($activeVersions): void {
            $settings = json_decode((string) $template->settings, true);
            $content = self::LEGACY_CONTENT;
            $isSelectedActiveVersion = (int) ($activeVersions[$template->type] ?? 0) === (int) $template->version;

            DB::table('document_templates')->where('id', $template->id)->update([
                'template_key' => $template->type.'-default',
                'status' => $isSelectedActiveVersion ? 'active' : 'archived',
                'content_html' => $content,
                'content_sha256' => hash('sha256', $content),
                'item_schema' => json_encode(is_array($settings) ? $settings : [], JSON_THROW_ON_ERROR),
                'default_terms' => json_encode([], JSON_THROW_ON_ERROR),
                'editor_config' => json_encode([], JSON_THROW_ON_ERROR),
                'activated_at' => $isSelectedActiveVersion ? $template->updated_at : null,
                'is_active' => $isSelectedActiveVersion,
            ]);
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropUnique('document_templates_type_version_unique');
            $table->unique(['type', 'template_key', 'version'], 'document_templates_family_version_unique');
            $table->index(['type', 'status'], 'document_templates_type_status_index');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->json('template_snapshot')->nullable()->after('item_schema');
            $table->char('template_content_sha256', 64)->nullable()->after('template_snapshot');
            $table->unsignedSmallInteger('placeholder_contract_version')->default(1)->after('template_content_sha256');
        });

        DB::table('quotations')->orderBy('id')->get()->each(function (object $quotation): void {
            $template = DB::table('document_templates')->where('id', $quotation->template_id)->first();
            if (! $template) {
                return;
            }

            $profile = DB::table('company_profiles')->where('id', $template->company_profile_id)->first();
            $snapshot = [
                'template_id' => $template->id,
                'template_key' => $template->template_key,
                'template_version' => (int) $template->version,
                'content_html' => $template->content_html,
                'item_schema' => json_decode((string) $template->item_schema, true) ?: [],
                'company_profile' => $profile ? [
                    'id' => $profile->id,
                    'company_code' => $profile->company_code,
                    'legal_name' => $profile->legal_name,
                    'display_name' => $profile->display_name,
                    'address_lines' => json_decode((string) $profile->address_lines, true) ?: [],
                    'city' => $profile->city,
                    'postal_code' => $profile->postal_code,
                    'country' => $profile->country,
                    'email' => $profile->email,
                    'phone' => $profile->phone,
                    'website' => $profile->website,
                    'logo_path' => $profile->logo_path,
                    'logo_sha256' => $profile->logo_sha256,
                    'primary_color' => $profile->primary_color,
                ] : null,
            ];

            DB::table('quotations')->where('id', $quotation->id)->update([
                'template_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'template_content_sha256' => $template->content_sha256,
                'placeholder_contract_version' => 1,
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_templates ALTER COLUMN template_key SET NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN content_html SET NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN content_sha256 SET NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN item_schema SET NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN default_terms SET NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN editor_config SET NOT NULL');
            DB::statement("ALTER TABLE document_templates ADD CONSTRAINT document_templates_status_check CHECK (status IN ('draft', 'active', 'archived'))");
            DB::statement("ALTER TABLE document_templates ADD CONSTRAINT document_templates_key_check CHECK (template_key ~ '^[a-z][a-z0-9]*(-[a-z0-9]+)*$')");
            DB::statement("CREATE UNIQUE INDEX document_templates_one_active_family_unique ON document_templates (type, template_key) WHERE status = 'active'");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX document_templates_one_active_family_unique ON document_templates (type, template_key) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_templates_one_active_family_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_status_check');
            DB::statement('ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_key_check');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN template_key DROP NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN content_html DROP NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN content_sha256 DROP NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN item_schema DROP NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN default_terms DROP NOT NULL');
            DB::statement('ALTER TABLE document_templates ALTER COLUMN editor_config DROP NOT NULL');
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['template_snapshot', 'template_content_sha256', 'placeholder_contract_version']);
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropIndex('document_templates_type_status_index');
            $table->dropUnique('document_templates_family_version_unique');
            $table->unique(['type', 'version'], 'document_templates_type_version_unique');
            $table->dropConstrainedForeignId('activated_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'template_key', 'status', 'content_html', 'content_sha256', 'item_schema',
                'default_intro_text', 'default_closing_text', 'default_terms', 'editor_config',
                'lock_version', 'activated_at',
            ]);
        });
    }
};
