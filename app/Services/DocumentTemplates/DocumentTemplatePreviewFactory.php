<?php

namespace App\Services\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\QuotationTerm;

final class DocumentTemplatePreviewFactory
{
    public function make(DocumentTemplate $template): Quotation
    {
        $template->loadMissing('companyProfile');
        $quotation = new Quotation([
            'quotation_date' => now()->toDateString(),
            'subject' => 'Penawaran Layanan Logistik',
            'customer_name' => 'PT Pelanggan Contoh',
            'customer_address' => "Jl. Contoh No. 123\nJakarta",
            'attention_name' => 'Bapak/Ibu Pelanggan',
            'attention_role' => 'Manajer Operasional',
            'sender_name' => 'Nama Penandatangan',
            'sender_title' => 'Direktur',
            'currency' => 'IDR',
            'intro_text' => $template->default_intro_text ?: 'Dengan hormat, berikut kami sampaikan penawaran layanan.',
            'closing_text' => $template->default_closing_text ?: 'Demikian penawaran ini kami sampaikan. Terima kasih.',
            'content_html' => '<table style="border-collapse: collapse; width: 100%" border="1"><thead><tr><th>Item</th><th>Deskripsi</th><th>Harga</th></tr></thead><tbody><tr><td>1</td><td>Item contoh</td><td>0</td></tr></tbody></table>',
            'template_snapshot' => $template->snapshot(),
            'template_content_sha256' => $template->content_sha256,
            'status' => 'draft',
        ]);
        $quotation->content_sha256 = hash('sha256', $quotation->content_html);
        $quotation->terms_html = '<ol><li>Harga belum termasuk pajak yang berlaku.</li><li>Masa berlaku penawaran adalah 30 hari.</li></ol>';
        $quotation->terms_sha256 = hash('sha256', $quotation->terms_html);

        $quotation->setRelation('document', null);
        $quotation->setRelation('terms', $this->terms($template));

        return $quotation;
    }

    private function terms(DocumentTemplate $template): \Illuminate\Support\Collection
    {
        $terms = $template->default_terms ?: [
            'Harga belum termasuk pajak yang berlaku.',
            'Masa berlaku penawaran adalah 30 hari.',
        ];

        return collect($terms)->values()->map(
            fn ($term, int $position) => new QuotationTerm([
                'position' => $position + 1,
                'content' => (string) $term,
            ]),
        );
    }
}
