<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CollectionService
{
    /**
     * توزيع مبلغ التحصيل على الفواتير
     *
     * @param  Collection  $collection  التحصيل المراد توزيعه
     */
    public function allocatePayment(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $remaining = (float) $collection->amount;

            // تحديد ترتيب الفواتير
            $order = $collection->distribution_method === 'newest_first'
                ? 'desc'
                : 'asc';

            // جلب الفواتير غير المسددة مع قفل للحماية من Race Condition
            $unpaidInvoices = Invoice::where('customer_id', $collection->customer_id)
                ->where('balance', '>', 0)
                ->where('status', 'active')
                ->orderBy('date', $order)
                ->lockForUpdate()
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $allocateAmount = min($remaining, (float) $invoice->balance);

                // إنشاء سجل التوزيع
                // Observer سيتولى تحديث الفاتورة
                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                ]);

                $remaining -= $allocateAmount;
            }

            // لو تبقى مبلغ، يصبح رصيد دائن للعميل
            // (customer.balance سالب تلقائياً من CollectionObserver)
        });
    }

    /**
     * التوزيع اليدوي على فاتورة محددة
     */
    public function allocateToInvoice(Collection $collection, Invoice $invoice): void
    {
        // Validate invoice belongs to customer
        if ($invoice->customer_id !== $collection->customer_id) {
            throw new BusinessException(
                'COL_003',
                'الفاتورة لا تخص هذا العميل',
                'Invoice does not belong to this customer'
            );
        }

        DB::transaction(function () use ($collection, $invoice) {
            $allocateAmount = min((float) $collection->amount, (float) $invoice->balance);

            CollectionAllocation::create([
                'collection_id' => $collection->id,
                'invoice_id' => $invoice->id,
                'amount' => $allocateAmount,
            ]);

            // الفائض يبقى كرصيد دائن
        });
    }

    /**
     * إلغاء توزيع التحصيل
     */
    public function reverseAllocations(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            // Observers ستتولى تحديث الفواتير
            $collection->allocations()->delete();
        });
    }

    /**
     * إعادة توزيع التحصيل (بعد إلغاء التوزيع القديم)
     */
    public function reallocate(Collection $collection): void
    {
        $this->reverseAllocations($collection);
        $this->allocatePayment($collection);
    }

    /**
     * الحصول على الفواتير غير المسددة للعميل
     */
    public function getUnpaidInvoices(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('customer_id', $customerId)
            ->where('balance', '>', 0)
            ->where('status', 'active')
            ->orderBy('date', 'asc')
            ->get(['id', 'invoice_number', 'date', 'total', 'balance']);
    }
}
