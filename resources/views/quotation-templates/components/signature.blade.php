<section class="signatures">
    <div><div class="signature-heading">Sincerely Yours,</div><div class="signature-space"></div><div class="signature-name">{{ $quotation->sender_name }}</div><div>{{ $quotation->sender_title }}</div></div>
    <div><div class="signature-heading">Approved By,</div><div class="signature-space"></div><div class="signature-name">{{ $quotation->attention_name ?: $quotation->customer_name }}</div></div>
</section>
