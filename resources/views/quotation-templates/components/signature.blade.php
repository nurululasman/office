<section class="signatures">
    <div>
        <div class="signature-heading">Sincerely Yours,</div>
        <div class="signature-space" style="position: relative; height: 28mm; min-height: 28mm;">
            @if(!empty($showSenderSignatureAndStamp))
                @if(!empty($stampSource))
                    <img src="{{ $stampSource }}" alt="Company Stamp" class="company-stamp-img" style="position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 28mm; height: 28mm; max-width: 28mm; max-height: 28mm; object-fit: contain; opacity: 0.82; z-index: 1; pointer-events: none;">
                @endif
                @if(!empty($signatureSource))
                    <img src="{{ $signatureSource }}" alt="Signature" class="user-signature-img" style="position: absolute; left: 10mm; top: 50%; transform: translateY(-50%); max-height: 24mm; max-width: 40mm; object-fit: contain; z-index: 2;">
                @endif
            @endif
        </div>
        <div class="signature-name">{{ $quotation->sender?->name ?: ($quotation->creator?->name ?: $quotation->sender_name) }}</div>
        <div>{{ $quotation->sender_title }}</div>
    </div>
    <div>
        <div class="signature-heading">Approved By,</div>
        <div class="signature-space" style="position: relative; height: 28mm; min-height: 28mm;"></div>
        <div class="signature-name">{{ $quotation->attention_name ?: $quotation->customer_name }}</div>
        <div>{{ $quotation->attention_role }}</div>
    </div>
</section>
