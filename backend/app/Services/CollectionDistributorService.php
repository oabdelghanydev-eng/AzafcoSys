<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CollectionDistributorService
{
    /**
     * Distribute collection amount to unpaid invoices
     * Supports FIFO (oldest_first) and LIFO (newest_first)
     * تصحيح 2025-12-13: إضافة دعم newest_first
     * 
     * @param Collection $collection
     * @return void
     */
    public function distributeAuto(Collection $collection): void
    {
        // Skip if manual distribution
        if ($collection->distribution_method === 'manual') {
            return;
        }

        DB::transaction(function () use ($collection) {
            $remaining = $collection->amount;

            // Determine sort order based on distribution method
            $sortDirection = $collection->distribution_method === 'newest_first' ? 'desc' : 'asc';

            // Get unpaid invoices for this customer
            // تصحيح 2025-12-13: إضافة lockForUpdate() لحماية Race Condition (BR-COL-005)
            $unpaidInvoices = Invoice::query()
                ->where('customer_id', $collection->customer_id)
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->orderBy('date', $sortDirection)
                ->orderBy('id', $sortDirection)
                ->lockForUpdate()
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                if ($remaining <= 0)
                    break;

                $allocateAmount = min($remaining, $invoice->balance);

                // Create allocation
                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                ]);

                $remaining -= $allocateAmount;
            }

            // Update collection totals
            $collection->allocated_amount = $collection->amount - $remaining;
            $collection->unallocated_amount = $remaining;
            $collection->saveQuietly();
        });
    }

    /**
     * Distribute collection manually to specific invoices
     * 
     * @param Collection $collection
     * @param array<int, float> $allocations [invoice_id => amount]
     * @throws \Exception If allocation exceeds collection amount
     */
    public function distributeManual(Collection $collection, array $allocations): void
    {
        DB::transaction(function () use ($collection, $allocations) {
            $totalAllocated = array_sum($allocations);

            if ($totalAllocated > $collection->amount) {
                throw new \Exception(
                    "إجمالي التوزيع ({$totalAllocated}) أكبر من مبلغ التحصيل ({$collection->amount})"
                );
            }

            // Remove existing allocations
            $collection->allocations()->delete();

            // Create new allocations
            foreach ($allocations as $invoiceId => $amount) {
                if ($amount <= 0)
                    continue;

                $invoice = Invoice::where('id', $invoiceId)
                    ->where('customer_id', $collection->customer_id)
                    ->where('status', 'active')
                    ->firstOrFail();

                if ($amount > $invoice->balance) {
                    throw new \Exception(
                        "المبلغ ({$amount}) أكبر من رصيد الفاتورة ({$invoice->balance})"
                    );
                }

                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                ]);
            }

            // Update collection totals
            $collection->allocated_amount = $totalAllocated;
            $collection->unallocated_amount = $collection->amount - $totalAllocated;
            $collection->saveQuietly();
        });
    }

    /**
     * Reverse all allocations for a collection
     * Called when collection is deleted
     * 
     * @param Collection $collection
     */
    public function reverseAllocations(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            // Delete allocations (observer will update invoice balances)
            $collection->allocations()->delete();

            // Reset collection totals
            $collection->allocated_amount = 0;
            $collection->unallocated_amount = $collection->amount;
            $collection->saveQuietly();
        });
    }

    /**
     * Get customer's unpaid invoices for manual allocation UI
     * 
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnpaidInvoices(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderBy('date', 'asc')
            ->get(['id', 'invoice_number', 'date', 'total', 'paid_amount', 'balance']);
    }
}
