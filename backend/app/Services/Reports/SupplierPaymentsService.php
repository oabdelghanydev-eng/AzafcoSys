<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Services\BaseService;

/**
 * SupplierPaymentsService
 * 
 * تقرير مدفوعات الموردين
 * 
 * يعرض المدفوعات والمصروفات لكل مورد
 * 
 * @package App\Services\Reports
 */
class SupplierPaymentsService extends BaseService
{
    /**
     * Generate supplier payments report.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $payments = $this->getPayments($dateFrom, $dateTo);
        $expenses = $this->getSupplierExpenses($dateFrom, $dateTo);

        $bySupplier = $this->groupBySupplier($payments, $expenses);

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'suppliers' => $bySupplier,
            'totals' => [
                'total_payments' => $payments->sum('amount'),
                'total_expenses' => $expenses->sum('amount'),
                'grand_total' => $payments->sum('amount') + $expenses->sum('amount'),
            ],
            'summary' => [
                'suppliers_count' => $bySupplier->count(),
                'transactions_count' => $payments->count() + $expenses->count(),
            ],
        ];
    }

    /**
     * Get payments to suppliers.
     */
    protected function getPayments(?string $dateFrom, ?string $dateTo)
    {
        return Expense::where('type', 'supplier_payment')
            ->with('supplier')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date->format('Y-m-d'),
                'supplier_id' => $e->supplier_id,
                'supplier_name' => $e->supplier->name ?? 'N/A',
                'amount' => (float) $e->amount,
                'payment_method' => $e->payment_method,
                'description' => $e->description,
            ]);
    }

    /**
     * Get expenses charged to suppliers.
     */
    protected function getSupplierExpenses(?string $dateFrom, ?string $dateTo)
    {
        return Expense::where('type', 'supplier')
            ->with(['supplier', 'shipment'])
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date->format('Y-m-d'),
                'supplier_id' => $e->supplier_id,
                'supplier_name' => $e->supplier->name ?? 'N/A',
                'shipment_number' => $e->shipment->number ?? 'N/A',
                'amount' => (float) $e->amount,
                'payment_method' => $e->payment_method,
                'description' => $e->description,
            ]);
    }

    /**
     * Group by supplier.
     */
    protected function groupBySupplier($payments, $expenses)
    {
        $suppliers = Supplier::where('is_active', true)->get();

        return $suppliers->map(function ($supplier) use ($payments, $expenses) {
            $supplierPayments = $payments->where('supplier_id', $supplier->id);
            $supplierExpenses = $expenses->where('supplier_id', $supplier->id);
            $paymentsTotal = $supplierPayments->sum('amount');
            $expensesTotal = $supplierExpenses->sum('amount');

            return [
                'supplier_id' => $supplier->id,
                'supplier_code' => $supplier->code,
                'supplier_name' => $supplier->name,
                'payments' => $paymentsTotal,
                'expenses' => $expensesTotal,
                'total' => $paymentsTotal + $expensesTotal,
                'transactions_count' => $supplierPayments->count() + $supplierExpenses->count(),
            ];
        })->filter(fn($s) => $s['payments'] > 0 || $s['expenses'] > 0)
            ->values();
    }

    protected function getServiceName(): string
    {
        return 'SupplierPaymentsService';
    }
}
