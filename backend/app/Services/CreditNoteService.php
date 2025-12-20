<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    private NumberGeneratorService $numberGenerator;

    public function __construct(NumberGeneratorService $numberGenerator)
    {
        $this->numberGenerator = $numberGenerator;
    }

    /**
     * Create a credit note (reduces customer balance)
     * إشعار دائن - يخفض رصيد العميل
     */
    public function createCreditNote(array $data): CreditNote
    {
        return $this->createNote('credit', $data);
    }

    /**
     * Create a debit note (increases customer balance)
     * إشعار مدين - يزيد رصيد العميل
     */
    public function createDebitNote(array $data): CreditNote
    {
        return $this->createNote('debit', $data);
    }

    /**
     * Create a credit/debit note
     */
    private function createNote(string $type, array $data): CreditNote
    {
        return DB::transaction(function () use ($type, $data) {
            $customer = Customer::findOrFail($data['customer_id']);

            // Validate invoice if provided
            if (!empty($data['invoice_id'])) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                if ($invoice->customer_id !== $customer->id) {
                    throw new BusinessException(
                        'CN_001',
                        'الفاتورة لا تخص هذا العميل',
                        'Invoice does not belong to this customer'
                    );
                }
            }

            $noteNumber = $this->numberGenerator->generate($type === 'credit' ? 'credit_note' : 'debit_note');

            $creditNote = CreditNote::create([
                'note_number' => $noteNumber,
                'type' => $type,
                'customer_id' => $customer->id,
                'invoice_id' => $data['invoice_id'] ?? null,
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Update customer balance
            // Credit note: decreases what customer owes (negative)
            // Debit note: increases what customer owes (positive)
            $balanceChange = $type === 'credit' ? -$data['amount'] : $data['amount'];
            $customer->increment('balance', $balanceChange);

            // Update invoice balance if linked
            if (!empty($data['invoice_id'])) {
                $invoice->increment('balance', $type === 'credit' ? -$data['amount'] : $data['amount']);
            }

            return $creditNote;
        });
    }

    /**
     * Cancel a credit/debit note
     */
    public function cancel(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->status === 'cancelled') {
            throw new BusinessException(
                'CN_002',
                'الإشعار ملغي بالفعل',
                'Note is already cancelled'
            );
        }

        return DB::transaction(function () use ($creditNote) {
            $customer = $creditNote->customer;

            // Reverse the balance change
            $reverseChange = $creditNote->type === 'credit' ? $creditNote->amount : -$creditNote->amount;
            $customer->increment('balance', $reverseChange);

            // Reverse invoice balance if linked
            if ($creditNote->invoice_id) {
                $creditNote->invoice->increment('balance', $reverseChange);
            }

            $creditNote->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Create price adjustment note
     * تسوية سعر - حساب الفرق تلقائياً
     */
    public function createPriceAdjustment(Invoice $invoice, array $adjustments): CreditNote
    {
        $totalDifference = 0;
        $reasons = [];

        foreach ($adjustments as $adjustment) {
            // adjustment: ['product_name' => 'X', 'old_price' => 50, 'new_price' => 45, 'quantity' => 10]
            $difference = ($adjustment['old_price'] - $adjustment['new_price']) * $adjustment['quantity'];
            $totalDifference += $difference;
            $reasons[] = sprintf(
                '%s: %s → %s (الكمية: %s)',
                $adjustment['product_name'],
                number_format($adjustment['old_price'], 2),
                number_format($adjustment['new_price'], 2),
                $adjustment['quantity']
            );
        }

        if ($totalDifference == 0) {
            throw new BusinessException(
                'CN_003',
                'لا يوجد فرق في الأسعار',
                'No price difference to adjust'
            );
        }

        $type = $totalDifference > 0 ? 'credit' : 'debit';

        return $this->createNote($type, [
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'amount' => abs($totalDifference),
            'reason' => 'تسوية أسعار فاتورة ' . $invoice->invoice_number,
            'notes' => implode("\n", $reasons),
        ]);
    }
}
