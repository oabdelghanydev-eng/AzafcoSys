<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Collection;
use App\Models\Correction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CorrectionService
 *
 * Handles soft-corrections for invoices and collections
 * Implements Maker-Checker approval workflow
 *
 * Best Practice: Never modify original records, create correction entries
 */
class CorrectionService
{
    public function __construct(
        private CollectionDistributorService $distributorService
    ) {
    }

    /**
     * Create invoice correction (adjustment note)
     * Creates a linked correction invoice that offsets the original
     *
     * @param  Invoice  $original  The original invoice to correct
     * @param  float  $adjustmentAmount  Positive = debit note, Negative = credit note
     * @param  string  $reason  Reason for correction
     * @param  array  $items  Optional items for the correction invoice
     * @return array ['correction' => Correction, 'invoice' => Invoice|null]
     */
    public function createInvoiceCorrection(
        Invoice $original,
        float $adjustmentAmount,
        string $reason,
        ?string $reasonCode = null
    ): array {
        return DB::transaction(function () use ($original, $adjustmentAmount, $reason, $reasonCode) {
            // Validate
            if ($original->status === 'cancelled') {
                throw new BusinessException(
                    ErrorCodes::INV_002,
                    'لا يمكن تصحيح فاتورة ملغاة',
                    'Cannot correct a cancelled invoice'
                );
            }

            // Calculate correction sequence
            $sequence = Correction::where('correctable_type', Invoice::class)
                ->where('correctable_id', $original->id)
                ->count() + 1;

            // Calculate new total after this correction
            $currentNetTotal = $this->getInvoiceNetTotal($original);
            $newNetTotal = $currentNetTotal + $adjustmentAmount;

            // Create correction record (pending approval)
            $correction = Correction::create([
                'correctable_type' => Invoice::class,
                'correctable_id' => $original->id,
                'correction_type' => Correction::TYPE_ADJUSTMENT,
                'original_value' => $original->total,
                'adjustment_value' => $adjustmentAmount,
                'new_value' => $newNetTotal,
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'correction_sequence' => $sequence,
                'status' => Correction::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            // Log
            AuditService::logCorrection(
                'correction_created',
                $original,
                ['correction_id' => $correction->id, 'amount' => $adjustmentAmount]
            );

            return [
                'correction' => $correction,
                'invoice' => null, // Will be created on approval
            ];
        });
    }

    /**
     * Approve invoice correction and create the adjustment invoice
     */
    public function approveInvoiceCorrection(Correction $correction, User $approver): Invoice
    {
        if (!$correction->isPending()) {
            throw new BusinessException(
                'COR_001',
                'التصحيح ليس في انتظار الموافقة',
                'Correction is not pending approval'
            );
        }

        if (!$correction->canBeApprovedBy($approver)) {
            throw new BusinessException(
                'COR_002',
                'لا يمكنك الموافقة على تصحيحك الخاص',
                'You cannot approve your own correction (Maker-Checker)'
            );
        }

        return DB::transaction(function () use ($correction, $approver) {
            /** @var Invoice $original */
            $original = $correction->correctable;

            // Create correction invoice
            $correctionInvoice = Invoice::create([
                'invoice_number' => $original->invoice_number . '-C' . $correction->correction_sequence,
                'customer_id' => $original->customer_id,
                'date' => now()->toDateString(),
                'type' => 'adjustment',
                'original_invoice_id' => $original->id,
                'correction_sequence' => $correction->correction_sequence,
                'subtotal' => abs((float) $correction->adjustment_value),
                'discount' => 0,
                'total' => $correction->adjustment_value,
                'balance' => $correction->adjustment_value,
                'paid_amount' => 0,
                'notes' => "تصحيح للفاتورة #{$original->invoice_number}: {$correction->reason}",
                'status' => 'active',
            ]);

            // Update correction status
            $correction->update([
                'status' => Correction::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            // CRITICAL: Update customer balance for correction invoice
            // SEV-1 FIX (2025-12-27): InvoiceObserver::created() does NOT update balance
            // Adjustment invoices can be positive (debit) or negative (credit)
            if ($correctionInvoice->type !== 'wastage') {
                Customer::where('id', $correctionInvoice->customer_id)
                    ->increment('balance', (float) $correctionInvoice->total);
            }

            AuditService::logCorrection(
                'correction_approved',
                $original,
                ['correction_id' => $correction->id, 'invoice_id' => $correctionInvoice->id]
            );

            return $correctionInvoice;
        });
    }

    /**
     * Create collection correction (supports negative for refunds)
     *
     * @param  Collection  $original  The original collection
     * @param  float  $adjustmentAmount  Positive = additional payment, Negative = refund
     * @param  string  $reason  Reason for correction
     */
    public function createCollectionCorrection(
        Collection $original,
        float $adjustmentAmount,
        string $reason,
        ?string $reasonCode = null
    ): array {
        return DB::transaction(function () use ($original, $adjustmentAmount, $reason, $reasonCode) {
            // Calculate sequence
            $sequence = Correction::where('correctable_type', Collection::class)
                ->where('correctable_id', $original->id)
                ->count() + 1;

            // Calculate new net amount
            $currentNetAmount = $this->getCollectionNetAmount($original);
            $newNetAmount = $currentNetAmount + $adjustmentAmount;

            // Create correction (pending)
            $correction = Correction::create([
                'correctable_type' => Collection::class,
                'correctable_id' => $original->id,
                'correction_type' => Correction::TYPE_ADJUSTMENT,
                'original_value' => $original->amount,
                'adjustment_value' => $adjustmentAmount,
                'new_value' => $newNetAmount,
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'correction_sequence' => $sequence,
                'status' => Correction::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            return [
                'correction' => $correction,
                'collection' => null,
            ];
        });
    }

    /**
     * Approve collection correction
     */
    public function approveCollectionCorrection(Correction $correction, User $approver): Collection
    {
        if (!$correction->canBeApprovedBy($approver)) {
            throw new BusinessException(
                'COR_002',
                'لا يمكنك الموافقة على تصحيحك الخاص',
                'You cannot approve your own correction'
            );
        }

        return DB::transaction(function () use ($correction, $approver) {
            /** @var Collection $original */
            $original = $correction->correctable;

            // Create correction collection (can be negative for refunds)
            $correctionCollection = Collection::create([
                'receipt_number' => $original->receipt_number . '-C' . $correction->correction_sequence,
                'customer_id' => $original->customer_id,
                'date' => now()->toDateString(),
                'amount' => $correction->adjustment_value, // Can be negative
                'payment_method' => $original->payment_method,
                'distribution_method' => 'manual', // Corrections are always manual
                'original_collection_id' => $original->id,
                'correction_sequence' => $correction->correction_sequence,
                'allocated_amount' => 0,
                'unallocated_amount' => $correction->adjustment_value,
                'notes' => "تصحيح للتحصيل #{$original->receipt_number}: {$correction->reason}",
            ]);

            $correction->update([
                'status' => Correction::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $correctionCollection;
        });
    }

    /**
     * Reject a correction
     */
    public function rejectCorrection(Correction $correction, User $rejector, string $reason): void
    {
        if (!$correction->isPending()) {
            throw new BusinessException(
                'COR_001',
                'التصحيح ليس في انتظار الموافقة',
                'Correction is not pending'
            );
        }

        $correction->update([
            'status' => Correction::STATUS_REJECTED,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditService::logCorrection(
            'correction_rejected',
            $correction->correctable,
            ['correction_id' => $correction->id, 'reason' => $reason]
        );
    }

    /**
     * Reallocate collection to different invoices
     */
    public function requestReallocation(
        Collection $collection,
        array $newAllocations,
        string $reason
    ): Correction {
        $sequence = Correction::where('correctable_type', Collection::class)
            ->where('correctable_id', $collection->id)
            ->count() + 1;

        return Correction::create([
            'correctable_type' => Collection::class,
            'correctable_id' => $collection->id,
            'correction_type' => Correction::TYPE_REALLOCATION,
            'original_value' => $collection->amount,
            'adjustment_value' => 0,
            'new_value' => $collection->amount,
            'reason' => $reason,
            'notes' => json_encode($newAllocations),
            'correction_sequence' => $sequence,
            'status' => Correction::STATUS_PENDING,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Approve reallocation
     */
    public function approveReallocation(Correction $correction, User $approver): void
    {
        if ($correction->correction_type !== Correction::TYPE_REALLOCATION) {
            throw new BusinessException('COR_003', 'ليس طلب إعادة توزيع', 'Not a reallocation request');
        }

        DB::transaction(function () use ($correction, $approver) {
            /** @var Collection $collection */
            $collection = $correction->correctable;
            $newAllocations = json_decode($correction->notes, true);

            // Reverse current allocations
            $this->distributorService->reverseAllocations($collection);

            // Apply new allocations manually
            foreach ($newAllocations as $allocation) {
                $this->distributorService->allocateToInvoice(
                    $collection,
                    Invoice::find($allocation['invoice_id']),
                    $allocation['amount']
                );
            }

            $correction->update([
                'status' => Correction::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
        });
    }

    // Helpers
    private function getInvoiceNetTotal(Invoice $invoice): float
    {
        $correctionsTotal = Correction::where('correctable_type', Invoice::class)
            ->where('correctable_id', $invoice->id)
            ->where('status', Correction::STATUS_APPROVED)
            ->sum('adjustment_value');

        return $invoice->total + $correctionsTotal;
    }

    private function getCollectionNetAmount(Collection $collection): float
    {
        $correctionsTotal = Correction::where('correctable_type', Collection::class)
            ->where('correctable_id', $collection->id)
            ->where('status', Correction::STATUS_APPROVED)
            ->sum('adjustment_value');

        return $collection->amount + $correctionsTotal;
    }
}
