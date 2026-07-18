<?php

namespace App\Services\Quotations;

use App\Exceptions\QuotationWorkflowException;
use App\Models\DocumentType;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberIssuer;
use App\Services\Documents\DocumentVoidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QuotationWorkflow
{
    public function __construct(
        private readonly DocumentNumberIssuer $issuer,
        private readonly AuditLogger $audit,
        private readonly DocumentVoidService $documentVoid,
        private readonly QuotationPdfDispatcher $pdfDispatcher,
    ) {}

    public function completeDirect(Quotation $quotation, User $actor, Request $request): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $request): Quotation {
            $locked = $this->lock($quotation);
            if ($locked->status === 'complete') {
                return $locked;
            }
            $this->assertVersion($locked, $request);
            if ($locked->approval_mode !== 'direct' || $locked->status !== 'draft') {
                throw new QuotationWorkflowException('Quotation tidak dapat diselesaikan melalui mode direct pada status saat ini.');
            }

            $before = $locked->toArray();
            $document = $this->issueDocument($locked, $actor);
            $now = now('UTC');
            $locked->update([
                'document_id' => $document->getKey(), 'status' => 'complete',
                'approval_bypassed_at' => $now, 'approval_bypassed_by' => $actor->getKey(),
                'approval_bypass_reason' => 'approval_mode_direct',
                'completed_at' => $now, 'completed_by' => $actor->getKey(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('quotation.approval_bypassed', $actor, $locked, before: $before, after: $locked->toArray(), context: ['reason' => 'approval_mode_direct'], request: $request);
            $this->audit->record('quotation.completed', $actor, $locked, before: $before, after: $locked->toArray(), context: ['document_number' => $document->number, 'path' => 'direct'], request: $request);
            $this->pdfDispatcher->dispatch($locked, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function submit(Quotation $quotation, User $actor, Request $request): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $request): Quotation {
            $locked = $this->lock($quotation);
            $this->assertVersion($locked, $request);
            if ($locked->approval_mode !== 'maker_checker' || $locked->status !== 'draft') {
                throw new QuotationWorkflowException('Hanya draft maker-checker yang dapat diajukan.');
            }

            $before = $locked->toArray();
            $locked->update(['status' => 'pending_approval', 'submitted_at' => now('UTC'), 'submitted_by' => $actor->getKey(), 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('quotation.submitted', $actor, $locked, before: $before, after: $locked->toArray(), request: $request);

            return $locked->refresh();
        }, 3);
    }

    public function approve(Quotation $quotation, User $actor, Request $request): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $request): Quotation {
            $locked = $this->lock($quotation);
            if ($locked->status === 'complete') {
                return $locked;
            }
            $this->assertVersion($locked, $request);
            if ($locked->approval_mode !== 'maker_checker' || $locked->status !== 'pending_approval') {
                throw new QuotationWorkflowException('Quotation tidak sedang menunggu approval.');
            }
            $this->assertDifferentChecker($locked, $actor);

            $before = $locked->toArray();
            $document = $this->issueDocument($locked, $actor);
            $now = now('UTC');
            $locked->update([
                'document_id' => $document->getKey(), 'status' => 'complete',
                'approved_at' => $now, 'approved_by' => $actor->getKey(),
                'completed_at' => $now, 'completed_by' => $actor->getKey(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('quotation.approved', $actor, $locked, before: $before, after: $locked->toArray(), request: $request);
            $this->audit->record('quotation.completed', $actor, $locked, before: $before, after: $locked->toArray(), context: ['document_number' => $document->number, 'path' => 'maker_checker'], request: $request);
            $this->pdfDispatcher->dispatch($locked, $actor);

            return $locked->refresh();
        }, 3);
    }

    public function reject(Quotation $quotation, User $actor, string $reason, Request $request): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $reason, $request): Quotation {
            $locked = $this->lock($quotation);
            $this->assertVersion($locked, $request);
            if ($locked->approval_mode !== 'maker_checker' || $locked->status !== 'pending_approval') {
                throw new QuotationWorkflowException('Quotation tidak sedang menunggu keputusan.');
            }
            $this->assertDifferentChecker($locked, $actor);

            $before = $locked->toArray();
            $locked->update([
                'status' => 'rejected', 'rejected_at' => now('UTC'), 'rejected_by' => $actor->getKey(),
                'rejection_reason' => trim($reason), 'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('quotation.rejected', $actor, $locked, before: $before, after: $locked->toArray(), context: ['reason' => trim($reason)], request: $request);

            return $locked->refresh();
        }, 3);
    }

    public function void(Quotation $quotation, User $actor, string $reason, Request $request): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $reason, $request): Quotation {
            $locked = $this->lock($quotation);
            if ($locked->status === 'void') {
                return $locked;
            }
            $this->assertVersion($locked, $request);
            if ($locked->status !== 'complete' || $locked->document_id === null) {
                throw new QuotationWorkflowException('Hanya quotation complete dengan nomor resmi yang dapat di-void.');
            }

            $before = $locked->toArray();
            $document = $this->documentVoid->void($locked->document()->firstOrFail(), $actor, $reason, $request);
            $locked->update(['status' => 'void', 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('quotation.voided', $actor, $locked, before: $before, after: $locked->toArray(), context: ['reason' => trim($reason), 'document_number' => $document->number], request: $request);

            return $locked->refresh();
        }, 3);
    }

    private function issueDocument(Quotation $quotation, User $actor)
    {
        $type = DocumentType::query()->where('code', config('office.quotation_document_type_code'))->first();
        if (! $type) {
            throw new QuotationWorkflowException('Tipe dokumen QUOTATION belum dikonfigurasi.');
        }

        return $this->issuer->issue($type, $actor, $quotation->subject, 'Quotation untuk '.$quotation->customer_name, $quotation);
    }

    private function lock(Quotation $quotation): Quotation
    {
        return Quotation::query()->lockForUpdate()->findOrFail($quotation->getKey());
    }

    private function assertVersion(Quotation $quotation, Request $request): void
    {
        if ((int) $request->input('lock_version', -1) !== $quotation->lock_version) {
            throw new QuotationWorkflowException('Quotation telah berubah. Muat ulang halaman sebelum melanjutkan.');
        }
    }

    private function assertDifferentChecker(Quotation $quotation, User $actor): void
    {
        if ($quotation->created_by === $actor->getKey() || $quotation->submitted_by === $actor->getKey()) {
            throw new QuotationWorkflowException('Pembuat atau pengaju quotation tidak boleh menyetujui atau menolak quotation sendiri.');
        }
    }
}
