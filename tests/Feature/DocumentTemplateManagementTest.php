<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class DocumentTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU',
            'legal_name' => 'PT JBLU',
            'display_name' => 'JBLU',
            'address_lines' => ['Jakarta'],
            'city' => 'Jakarta',
            'postal_code' => '10110',
            'country' => 'ID',
        ]);
    }

    public function test_document_admin_can_create_view_and_update_draft(): void
    {
        $admin = $this->userWithRole('document-admin');

        $this->as($admin)->get(route('quotation-templates.index'))->assertOk()
            ->assertSee('Template Quotation')
            ->assertSee('Buat template');
        $this->as($admin)->get(route('quotation-templates.create'))->assertOk()
            ->assertSee('Konten dokumen')
            ->assertSee('libs/tinymce/tinymce.min.js', false)
            ->assertSee('js/quotation-template-editor.js', false)
            ->assertSee('Jika JavaScript gagal dimuat');

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload())
            ->assertRedirect();

        $template = DocumentTemplate::query()->sole();
        $this->assertSame('quotation-general', $template->template_key);
        $this->assertSame('draft', $template->status);
        $this->assertSame(['Term one', 'Term two'], $template->default_terms);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation_template.created']);

        $this->as($admin)->get(route('quotation-templates.show', $template))->assertOk()
            ->assertSee('Preview visual')
            ->assertSee('data-template-preview="true"', false)
            ->assertSee('Service contoh 1')
            ->assertDontSee('Preview visual melalui renderer akan ditambahkan')
            ->assertSee('{{ quotation_items }}');
        $this->as($admin)->get(route('quotation-templates.preview', $template))->assertOk()
            ->assertSee('data-template-rendered="true"', false)
            ->assertSee('Service contoh 1')
            ->assertDontSee('{{ quotation_items }}');
        $this->assertStringNotContainsString('<script', $template->content_html);

        $this->as($admin)->put(route('quotation-templates.update', $template), $this->payload([
            'name' => 'General updated',
            'lock_version' => 0,
        ]))->assertRedirect(route('quotation-templates.show', $template));

        $this->assertSame('General updated', $template->refresh()->name);
        $this->assertSame(1, $template->lock_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation_template.updated']);
    }

    public function test_validation_rejects_duplicate_family_invalid_schema_and_stale_update(): void
    {
        $admin = $this->userWithRole('document-admin');
        $template = $this->template('quotation-general', 1, 'draft');

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload())
            ->assertSessionHasErrors('template_key');

        $this->as($admin)->put(route('quotation-templates.update', $template), $this->payload([
            'item_schema_json' => '{"invalid":true}',
            'lock_version' => 0,
        ]))->assertSessionHasErrors('item_schema_json');

        $this->as($admin)->put(route('quotation-templates.update', $template), $this->payload([
            'item_schema_json' => json_encode([
                'columns' => [['key' => 'service', 'label' => 'Service', 'value_type' => 'text']],
                'presentation' => ['type' => 'nested_list', 'content_key' => 'missing', 'max_depth' => 3],
            ], JSON_THROW_ON_ERROR),
            'lock_version' => 0,
        ]))->assertSessionHasErrors('item_schema_json');

        $template->update(['lock_version' => 1]);
        $this->as($admin)->put(route('quotation-templates.update', $template), $this->payload([
            'name' => 'Stale',
            'lock_version' => 0,
        ]))->assertSessionHasErrors('template');

        $this->assertSame('Template', $template->fresh()->name);
    }

    public function test_duplicate_activate_and_archive_actions_follow_lifecycle(): void
    {
        $admin = $this->userWithRole('document-admin');
        $active = $this->template('quotation-general', 1, 'active');

        $this->as($admin)->post(route('quotation-templates.duplicate', $active))
            ->assertRedirect();
        $draft = DocumentTemplate::query()->where('version', 2)->sole();

        $this->as($admin)->post(route('quotation-templates.activate', $draft), ['lock_version' => 0])
            ->assertRedirect(route('quotation-templates.show', $draft));

        $this->assertSame('archived', $active->refresh()->status);
        $this->assertSame('active', $draft->refresh()->status);

        $other = $this->template('quotation-other', 1, 'active');
        $this->as($admin)->post(route('quotation-templates.archive', $draft), ['lock_version' => 1])
            ->assertRedirect(route('quotation-templates.show', $draft));
        $this->assertSame('archived', $draft->refresh()->status);
        $this->assertSame('active', $other->refresh()->status);

        $this->assertSame(2, AuditLog::query()->where('action', 'quotation_template.archived')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'quotation_template.activated')->count());
    }

    public function test_auditor_is_read_only_and_unprivileged_user_is_forbidden(): void
    {
        $auditor = $this->userWithRole('auditor');
        $basic = $this->userWithRole('office-user');
        $template = $this->template();

        $this->as($auditor)->get(route('quotation-templates.index'))->assertOk();
        $this->as($auditor)->get(route('quotation-templates.show', $template))->assertOk()
            ->assertDontSee('Ubah draft');
        $this->as($auditor)->get(route('quotation-templates.create'))->assertForbidden();
        $this->as($basic)->get(route('quotation-templates.index'))->assertForbidden();
        $this->as($basic)->post(route('quotation-templates.duplicate', $template))->assertForbidden();
    }

    public function test_mutation_routes_are_rate_limited(): void
    {
        foreach ([
            'quotation-templates.store',
            'quotation-templates.update',
            'quotation-templates.duplicate',
            'quotation-templates.activate',
            'quotation-templates.archive',
        ] as $routeName) {
            $this->assertContains('throttle:office-mutation', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_tinymce_uses_local_assets_and_controlled_plugins(): void
    {
        $this->assertFileExists(public_path('libs/tinymce/tinymce.min.js'));
        $this->assertFileExists(public_path('libs/tinymce/skins/ui/oxide/skin.min.css'));
        $this->assertFileExists(public_path('libs/tinymce/skins/content/document/content.min.css'));

        $configuration = file_get_contents(public_path('js/quotation-template-editor.js'));
        $this->assertIsString($configuration);
        $this->assertStringContainsString("base_url: '/libs/tinymce'", $configuration);
        $this->assertStringContainsString('advlist autolink autoresize code lists pagebreak preview searchreplace table visualblocks wordcount', $configuration);
        $this->assertStringContainsString('paste_as_text: true', $configuration);
        $this->assertStringNotContainsString('tiny.cloud', $configuration);
        $this->assertStringNotContainsString("plugins: 'image", $configuration);
        $this->assertStringNotContainsString(' media ', $configuration);
        $this->assertStringContainsString("editor.ui.registry.addMenuButton('placeholders'", $configuration);
        $this->assertStringContainsString('quotation_items', $configuration);
    }

    public function test_server_sanitizes_html_and_rejects_unknown_or_malformed_placeholders(): void
    {
        $admin = $this->userWithRole('document-admin');

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload([
            'template_key' => 'quotation-sanitized',
            'content_html' => '<p onclick="alert(1)" style="text-align:center;color:red">Penawaran é</p>'
                .'<iframe src="https://example.test"></iframe>'
                .'<a href="javascript:alert(1)">Link text</a>'
                .'<div>{{ quotation_items }}</div>',
        ]))->assertRedirect();

        $content = DocumentTemplate::query()->where('template_key', 'quotation-sanitized')->sole()->content_html;
        $this->assertStringContainsString('Penawaran é', $content);
        $this->assertStringContainsString('text-align: center', $content);
        $this->assertStringContainsString('Link text', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('color:', $content);
        $this->assertStringNotContainsString('iframe', $content);
        $this->assertStringNotContainsString('javascript:', $content);

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload([
            'template_key' => 'quotation-unknown',
            'content_html' => '<p>{{ customer_secret }}</p><div>{{ quotation_items }}</div>',
        ]))->assertSessionHasErrors('template');

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload([
            'template_key' => 'quotation-malformed',
            'content_html' => '<p>{{ customer_name </p><div>{{ quotation_items }}</div>',
        ]))->assertSessionHasErrors('template');
    }

    public function test_sanitizer_enforces_step_seven_html_and_asset_boundary(): void
    {
        $html = <<<'HTML'
        <!-- hidden -->
        <script>alert('script')</script>
        <style>body { background: red }</style>
        <iframe src="https://example.test">frame</iframe>
        <img src="https://example.test/logo.png" onerror="alert(1)">
        <a href="https://example.test" onclick="alert(2)">External label</a>
        <p class="unknown page-break" style="text-align:RIGHT; background:url(https://example.test/x); position:fixed">Safe text</p>
        <table border="9999" style="width:100%; border-collapse:collapse; color:red"><tr><td colspan="2" rowspan="invalid" style="vertical-align:TOP">Cell</td></tr></table>
        <hr class="unknown page-break">
        <div>{{ quotation_items }}</div>
        HTML;

        $sanitized = app(DocumentTemplateHtmlSanitizer::class)->sanitize($html);

        $this->assertStringNotContainsString('hidden', $sanitized);
        $this->assertStringNotContainsString('script', $sanitized);
        $this->assertStringNotContainsString('iframe', $sanitized);
        $this->assertStringNotContainsString('<img', $sanitized);
        $this->assertStringNotContainsString('href=', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('url(', $sanitized);
        $this->assertStringNotContainsString('position', $sanitized);
        $this->assertStringContainsString('External label', $sanitized);
        $this->assertStringContainsString('class="page-break"', $sanitized);
        $this->assertStringContainsString('text-align: right', $sanitized);
        $this->assertStringContainsString('width: 100%; border-collapse: collapse', $sanitized);
        $this->assertStringContainsString('colspan="2"', $sanitized);
        $this->assertStringNotContainsString('rowspan=', $sanitized);
        $this->assertStringContainsString('vertical-align: top', $sanitized);
    }

    public function test_sanitizer_rejects_oversized_html_outside_http_validation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('200.000 byte');

        app(DocumentTemplateHtmlSanitizer::class)->sanitize(
            '<p>'.str_repeat('a', DocumentTemplateHtmlSanitizer::MAX_HTML_BYTES).'</p>',
        );
    }

    public function test_activation_requires_minimum_placeholders_and_structural_placement(): void
    {
        $admin = $this->userWithRole('document-admin');
        $incomplete = $this->template('quotation-incomplete');

        $this->as($admin)->post(route('quotation-templates.activate', $incomplete), ['lock_version' => 0])
            ->assertSessionHasErrors('template');
        $this->assertSame('draft', $incomplete->fresh()->status);

        $this->as($admin)->post(route('quotation-templates.store'), $this->payload([
            'template_key' => 'quotation-bad-placement',
            'content_html' => '<p>Before {{ quotation_items }}</p>',
        ]))->assertSessionHasErrors('template');
    }

    public function test_customer_address_and_sender_title_placeholders_are_optional_for_activation(): void
    {
        $admin = $this->userWithRole('document-admin');
        $template = $this->template('quotation-without-customer-address');
        $template->update([
            'content_html' => str_replace(
                ['<p>{{ customer_address }}</p>', '<p>{{ sender_title }}</p>'],
                ['', ''],
                DocumentTemplate::LEGACY_CONTENT_HTML,
            ),
        ]);

        $this->as($admin)
            ->post(route('quotation-templates.activate', $template), ['lock_version' => 0])
            ->assertRedirect(route('quotation-templates.show', $template));

        $this->assertSame('active', $template->refresh()->status);
        $this->assertStringNotContainsString('{{ customer_address }}', $template->content_html);
        $this->assertStringNotContainsString('{{ sender_title }}', $template->content_html);
    }

    public function test_activation_resanitizes_content_created_outside_lifecycle_service(): void
    {
        $admin = $this->userWithRole('document-admin');
        $template = $this->template('quotation-direct-model');
        $template->update([
            'content_html' => '<script>alert(1)</script>'.DocumentTemplate::LEGACY_CONTENT_HTML,
        ]);

        $this->as($admin)->post(route('quotation-templates.activate', $template), ['lock_version' => 0])
            ->assertRedirect();

        $this->assertSame('active', $template->refresh()->status);
        $this->assertStringNotContainsString('<script', $template->content_html);
        $this->assertStringNotContainsString('alert(1)', $template->content_html);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'company_profile_id' => $this->profile->getKey(),
            'template_key' => 'quotation-general',
            'name' => 'General',
            'content_html' => '<script>not executed</script><div>{{ quotation_items }}</div>',
            'item_schema_json' => json_encode(['columns' => [
                ['key' => 'service', 'label' => 'Service', 'value_type' => 'text'],
            ]], JSON_THROW_ON_ERROR),
            'default_intro_text' => 'Intro',
            'default_closing_text' => 'Closing',
            'default_terms_text' => "Term one\nTerm two",
        ], $overrides);
    }

    private function template(
        string $key = 'quotation-general',
        int $version = 1,
        string $status = 'draft',
    ): DocumentTemplate {
        return DocumentTemplate::query()->create([
            'company_profile_id' => $this->profile->getKey(),
            'type' => 'quotation',
            'template_key' => $key,
            'version' => $version,
            'name' => 'Template',
            'status' => $status,
            'content_html' => $status === 'draft' && $key === 'quotation-incomplete'
                ? '<div>{{ quotation_items }}</div>'
                : DocumentTemplate::LEGACY_CONTENT_HTML,
            'item_schema' => ['columns' => []],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted',
            'refresh_token' => null,
            'expires_at' => time() + 3600,
            'authenticated_at' => time(),
        ]]);
    }
}
