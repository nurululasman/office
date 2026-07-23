<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentTemplates\DocumentTemplateLifecycle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class DocumentTemplateAuthorizationTest extends TestCase
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

    public function test_role_matrix_and_policy_are_fail_closed(): void
    {
        $admin = $this->userWithRole('document-admin');
        $auditor = $this->userWithRole('auditor');
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->draftTemplate();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', DocumentTemplate::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', DocumentTemplate::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $template));
        $this->assertTrue(Gate::forUser($admin)->allows('activate', $template));
        $this->assertTrue(Gate::forUser($admin)->allows('archive', $template));

        $this->assertTrue(Gate::forUser($auditor)->allows('view', $template));
        $this->assertFalse(Gate::forUser($auditor)->allows('update', $template));
        $this->assertFalse(Gate::forUser($maker)->allows('view', $template));
        $this->assertFalse(Gate::forUser($maker)->allows('create', DocumentTemplate::class));

        $template->update(['status' => 'archived']);
        $this->assertFalse(Gate::forUser($admin)->allows('update', $template));
        $this->assertFalse(Gate::forUser($admin)->allows('activate', $template));
    }

    public function test_lifecycle_creates_updates_and_versions_draft_with_sanitized_audit_summary(): void
    {
        $admin = $this->userWithRole('document-admin');
        $lifecycle = app(DocumentTemplateLifecycle::class);

        $template = $lifecycle->createDraft([
            'company_profile_id' => $this->profile->getKey(),
            'template_key' => 'quotation-general',
            'version' => 1,
            'name' => 'General',
            'content_html' => '<p>Secret body</p><div>{{ quotation_items }}</div>',
            'item_schema' => ['columns' => []],
        ], $admin);

        $this->assertSame('draft', $template->status);
        $this->assertFalse($template->is_active);
        $this->assertSame($admin->getKey(), $template->created_by);

        $updated = $lifecycle->updateDraft($template, [
            'name' => 'General updated',
            'status' => 'active',
            'version' => 99,
        ], 0, $admin);

        $this->assertSame('draft', $updated->status);
        $this->assertSame(1, $updated->version);
        $this->assertSame(1, $updated->lock_version);

        $copy = $lifecycle->createVersion($updated, $admin);
        $this->assertSame(2, $copy->version);
        $this->assertSame('draft', $copy->status);
        $this->assertSame($updated->content_sha256, $copy->content_sha256);

        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation_template.created', 'subject_id' => $template->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation_template.updated', 'subject_id' => $template->getKey()]);
        $versionAudit = AuditLog::query()->where('action', 'quotation_template.version_created')->sole();
        $this->assertSame($updated->getKey(), $versionAudit->context['source_template_id']);
        $this->assertArrayNotHasKey('content_html', $versionAudit->after);
        $this->assertSame($copy->content_sha256, $versionAudit->after['content_sha256']);
    }

    public function test_activation_atomically_archives_previous_version_and_audits_both(): void
    {
        $admin = $this->userWithRole('document-admin');
        $active = $this->activeTemplate('quotation-general', 1);
        $draft = $this->draftTemplate('quotation-general', 2);

        $activated = app(DocumentTemplateLifecycle::class)->activate($draft, 0, $admin);

        $this->assertSame('archived', $active->refresh()->status);
        $this->assertFalse($active->is_active);
        $this->assertSame('active', $activated->status);
        $this->assertTrue($activated->is_active);
        $this->assertSame($admin->getKey(), $activated->activated_by);
        $this->assertNotNull($activated->activated_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'quotation_template.activated')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'quotation_template.archived')->count());
    }

    public function test_last_active_quotation_template_cannot_be_archived(): void
    {
        $admin = $this->userWithRole('document-admin');
        $template = $this->activeTemplate();

        $this->expectException(LogicException::class);
        app(DocumentTemplateLifecycle::class)->archive($template, 0, $admin);
    }

    public function test_stale_update_is_rejected_without_audit_or_partial_change(): void
    {
        $admin = $this->userWithRole('document-admin');
        $template = $this->draftTemplate();
        $template->update(['lock_version' => 1]);

        try {
            app(DocumentTemplateLifecycle::class)->updateDraft($template, ['name' => 'Stale write'], 0, $admin);
            $this->fail('Expected stale update to be rejected.');
        } catch (LogicException) {
            $this->assertSame('Template', $template->fresh()->name);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'quotation_template.updated']);
        }
    }

    public function test_lifecycle_service_rejects_unauthorized_actor(): void
    {
        $maker = $this->userWithRole('quotation-maker');

        $this->expectException(AuthorizationException::class);
        app(DocumentTemplateLifecycle::class)->createDraft([
            'company_profile_id' => $this->profile->getKey(),
            'template_key' => 'quotation-denied',
            'version' => 1,
            'name' => 'Denied',
            'item_schema' => ['columns' => []],
        ], $maker);
    }

    private function activeTemplate(string $key = 'quotation-general', int $version = 1): DocumentTemplate
    {
        return DocumentTemplate::query()->create([
            'company_profile_id' => $this->profile->getKey(),
            'type' => 'quotation',
            'template_key' => $key,
            'version' => $version,
            'name' => 'Template',
            'status' => 'active',
            'settings' => ['columns' => []],
        ]);
    }

    private function draftTemplate(string $key = 'quotation-general', int $version = 1): DocumentTemplate
    {
        return DocumentTemplate::query()->create([
            'company_profile_id' => $this->profile->getKey(),
            'type' => 'quotation',
            'template_key' => $key,
            'version' => $version,
            'name' => 'Template',
            'status' => 'draft',
            'settings' => ['columns' => []],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
