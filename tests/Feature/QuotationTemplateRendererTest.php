<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\DocumentTemplates\QuotationTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class QuotationTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_uses_snapshot_and_escapes_scalar_and_structural_values(): void
    {
        [$quotation, $template] = $this->quotation();
        $item = $quotation->items()->create(['position' => 1]);
        $item->values()->createMany([
            ['key' => 'service', 'value' => '<img src=x onerror=alert(1)>', 'value_type' => 'text', 'position' => 1],
            ['key' => 'price', 'value' => '125000.50', 'value_type' => 'currency', 'position' => 2],
        ]);
        $quotation->terms()->create(['position' => 1, 'content' => '<script>alert(1)</script>']);

        $html = app(QuotationTemplateRenderer::class)->render(
            $quotation,
            isDraft: true,
            logoSource: 'data:image/png;base64,ZmFrZQ==',
        );

        $this->assertStringContainsString('PT JBLU', $html);
        $this->assertStringContainsString('DRAFT — nomor belum terbit', $html);
        $this->assertStringContainsString('18 Juli 2026', $html);
        $this->assertStringContainsString('Customer &lt;b&gt;unsafe&lt;/b&gt;', $html);
        $this->assertStringContainsString('Line 1<br>', $html);
        $this->assertStringContainsString('Line 2', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('Rp 125.001', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('data:image/png;base64,ZmFrZQ==', $html);
        $this->assertStringContainsString('class="draft-watermark"', $html);
        $this->assertStringContainsString('class="signatures"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('{{', $html);

        $snapshotHtml = $html;
        $template->update([
            'content_html' => DocumentTemplate::LEGACY_CONTENT_HTML,
            'item_schema' => ['columns' => []],
        ]);

        $this->assertSame(
            $snapshotHtml,
            app(QuotationTemplateRenderer::class)->render(
                $quotation->fresh(),
                isDraft: true,
                logoSource: 'data:image/png;base64,ZmFrZQ==',
            ),
        );
    }

    public function test_renderer_omits_draft_only_fragment_for_official_output(): void
    {
        [$quotation] = $this->quotation();

        $html = app(QuotationTemplateRenderer::class)->render($quotation, isDraft: false);

        $this->assertStringNotContainsString('class="draft-watermark"', $html);
        $this->assertStringNotContainsString('company-logo', $html);
    }

    public function test_renderer_fails_closed_when_snapshot_checksum_is_tampered(): void
    {
        [$quotation] = $this->quotation();
        $snapshot = $quotation->template_snapshot;
        $snapshot['content_html'] = '<p>Tampered</p>';
        $quotation->update(['template_snapshot' => $snapshot]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum snapshot template quotation tidak cocok.');

        app(QuotationTemplateRenderer::class)->render($quotation->fresh());
    }

    public function test_renderer_fails_closed_without_snapshot(): void
    {
        [$quotation] = $this->quotation();
        $quotation->update(['template_snapshot' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quotation belum memiliki snapshot template yang valid.');

        app(QuotationTemplateRenderer::class)->render($quotation->fresh());
    }

    public function test_renderer_supports_flat_ordered_list(): void
    {
        [$quotation] = $this->quotation([
            'type' => 'list',
            'style' => 'ordered',
            'content_key' => 'service',
        ]);
        foreach (['First item', 'Second <unsafe>'] as $position => $content) {
            $item = $quotation->items()->create(['position' => $position + 1]);
            $item->values()->create([
                'key' => 'service', 'value' => $content, 'value_type' => 'text', 'position' => 1,
            ]);
        }

        $html = app(QuotationTemplateRenderer::class)->render($quotation);

        $this->assertStringContainsString('<ol class="quotation-item-list level-1">', $html);
        $this->assertStringContainsString('<li>First item</li>', $html);
        $this->assertStringContainsString('Second &lt;unsafe&gt;', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_renderer_supports_nested_list_to_configured_depth(): void
    {
        [$quotation] = $this->quotation([
            'type' => 'nested_list',
            'style' => 'unordered',
            'content_key' => 'service',
            'max_depth' => 3,
        ]);
        $root = $this->listItem($quotation, 1, 'Root');
        $child = $this->listItem($quotation, 2, 'Child', $root->getKey());
        $this->listItem($quotation, 3, 'Grandchild', $child->getKey());

        $html = app(QuotationTemplateRenderer::class)->render($quotation);

        $this->assertStringContainsString('<ul class="quotation-item-list level-1">', $html);
        $this->assertStringContainsString('<ul class="quotation-item-list level-2">', $html);
        $this->assertStringContainsString('<ul class="quotation-item-list level-3">', $html);
        $this->assertMatchesRegularExpression('/Root.*Child.*Grandchild/s', $html);
    }

    public function test_renderer_rejects_depth_overflow_and_cycles(): void
    {
        [$quotation] = $this->quotation([
            'type' => 'nested_list',
            'content_key' => 'service',
            'max_depth' => 2,
        ]);
        $root = $this->listItem($quotation, 1, 'Root');
        $child = $this->listItem($quotation, 2, 'Child', $root->getKey());
        $this->listItem($quotation, 3, 'Too deep', $child->getKey());

        try {
            app(QuotationTemplateRenderer::class)->render($quotation);
            $this->fail('Expected max-depth guard.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('max depth', $exception->getMessage());
        }

        [$cyclic] = $this->quotation([
            'type' => 'nested_list',
            'content_key' => 'service',
            'max_depth' => 3,
        ]);
        $first = $this->listItem($cyclic, 1, 'First');
        $second = $this->listItem($cyclic, 2, 'Second', $first->getKey());
        $first->update(['parent_item_id' => $second->getKey()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('circular reference');
        app(QuotationTemplateRenderer::class)->render($cyclic->fresh());
    }

    /** @return array{Quotation, DocumentTemplate} */
    private function quotation(array $presentation = []): array
    {
        $suffix = Str::lower(Str::random(8));
        $user = User::factory()->create();
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU-'.$suffix,
            'legal_name' => 'PT Jaya Berlian Logistics Utama',
            'display_name' => 'PT JBLU',
            'address_lines' => ['Jl. Pelabuhan'],
            'city' => 'Jakarta',
            'postal_code' => '10110',
            'country' => 'ID',
            'email' => 'office@example.test',
        ]);
        $schema = ['columns' => [
            ['key' => 'service', 'label' => 'Service', 'value_type' => 'text', 'required' => true],
            ['key' => 'price', 'label' => 'Price', 'value_type' => 'currency', 'required' => true],
        ]];
        if ($presentation !== []) {
            $schema['presentation'] = $presentation;
        }
        $template = DocumentTemplate::query()->create([
            'company_profile_id' => $profile->getKey(),
            'type' => 'quotation',
            'template_key' => 'quotation-renderer-'.$suffix,
            'version' => 1,
            'name' => 'Renderer',
            'status' => 'active',
            'content_html' => implode('', [
                '<div>{{ draft_watermark }}</div>',
                '<h1>{{ company_display_name }}</h1>',
                '<div>{{ company_logo }}</div>',
                '<p>{{ quotation_number }}</p>',
                '<p>{{ quotation_date }}</p>',
                '<p>{{ subject }}</p>',
                '<p>{{ customer_name }}</p>',
                '<p>{{ customer_address }}</p>',
                '<p>{{ sender_name }}</p>',
                '<p>{{ sender_title }}</p>',
                '<div>{{ quotation_items }}</div>',
                '<div>{{ quotation_terms }}</div>',
                '<div>{{ signature_block }}</div>',
            ]),
            'item_schema' => $schema,
        ]);
        $quotation = Quotation::query()->create([
            'created_by' => $user->getKey(),
            'template_id' => $template->getKey(),
            'status' => 'draft',
            'approval_mode' => 'direct',
            'quotation_date' => '2026-07-18',
            'subject' => 'Storage',
            'customer_name' => 'Customer <b>unsafe</b>',
            'customer_address' => "Line 1\nLine 2",
            'attention_name' => 'Budi',
            'sender_name' => 'Sales',
            'sender_title' => 'Manager',
            'item_schema' => $schema,
            'currency' => 'IDR',
        ]);

        return [$quotation, $template];
    }

    private function listItem(Quotation $quotation, int $position, string $content, ?string $parentId = null): QuotationItem
    {
        $item = $quotation->items()->create([
            'parent_item_id' => $parentId,
            'position' => $position,
        ]);
        $item->values()->create([
            'key' => 'service',
            'value' => $content,
            'value_type' => 'text',
            'position' => 1,
        ]);

        return $item;
    }
}
