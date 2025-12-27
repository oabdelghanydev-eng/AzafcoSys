<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Supplier;
use App\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * SupplierStatementService
 * 
 * Generates supplier account statements showing shipment settlements and expenses.
 * 
 * Balance = مستحقات المورد (المبيعات - العمولة - المصروفات)
 * 
 * @package App\Services\Reports
 */
class SupplierStatementService extends BaseService
{
    /**
     * Generate supplier statement.
     *
     * @param Supplier $supplier The supplier
     * @param string|null $dateFrom Start date filter
     * @param string|null $dateTo End date filter
     * @return array Statement data
     */
    public function generateStatement(Supplier $supplier, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $shipments = $this->getShipments($supplier, $dateFrom, $dateTo);
        $expenses = $this->getExpenses($supplier, $dateFrom, $dateTo);
        $payments = $this->getPayments($supplier, $dateFrom, $dateTo);

        $timeline = $this->buildTimeline($shipments, $expenses, $payments, $supplier);

        // حساب الملخص
        $totalSettlements = $shipments->sum('final_supplier_balance');
        $totalExpenses = $expenses->sum('amount');
        $totalPayments = $payments->sum('amount');

        return [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->supplier_code,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'opening_balance' => (float) $supplier->opening_balance,
                'current_balance' => (float) $supplier->balance,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'summary' => [
                'opening_balance' => (float) $supplier->opening_balance,
                'total_shipments' => (float) $totalSettlements, // صافي المستحقات بعد العمولة
                'total_expenses' => (float) $totalExpenses,       // مصروفات على المورد
                'total_payments' => (float) $totalPayments,       // مدفوعات للمورد
                'closing_balance' => (float) ($supplier->opening_balance + $totalSettlements - $totalExpenses - $totalPayments),
                'shipments_count' => $shipments->count(),
                'expenses_count' => $expenses->count(),
                'payments_count' => $payments->count(),
            ],
            'transactions' => $timeline,
        ];
    }

    /**
     * Get supplier settled shipments for period.
     * يجب أن تكون الشحنة مُصفاة لتظهر في الكشف
     */
    protected function getShipments(Supplier $supplier, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $supplier->shipments()
            ->where('status', 'settled')
            ->when($dateFrom, fn($q) => $q->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('settled_at', '<=', $dateTo))
            ->orderBy('settled_at')
            ->get([
                'id',
                'number',
                'date',
                'settled_at',
                'total_sales',
                'total_supplier_expenses',
                'previous_supplier_balance',
                'final_supplier_balance'
            ]);
    }

    /**
     * Get supplier expenses (deducted from balance).
     * مصروفات على المورد - تُخصم من رصيده
     */
    protected function getExpenses(Supplier $supplier, ?string $dateFrom, ?string $dateTo): Collection
    {
        return Expense::where('supplier_id', $supplier->id)
            ->where('type', 'supplier')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'expense_number', 'date', 'amount', 'description']);
    }

    /**
     * Get payments to supplier.
     * مدفوعات للمورد
     */
    protected function getPayments(Supplier $supplier, ?string $dateFrom, ?string $dateTo): Collection
    {
        return Expense::where('supplier_id', $supplier->id)
            ->where('type', 'supplier_payment')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'expense_number', 'date', 'amount', 'description']);
    }

    /**
     * Build transaction timeline with running balance.
     * 
     * Debit (له - يزيد رصيده): Settlement (net after commission)
     * Credit (عليه - يقلل رصيده): Expenses, Payments
     */
    protected function buildTimeline(
        Collection $shipments,
        Collection $expenses,
        Collection $payments,
        Supplier $supplier
    ): Collection {
        $timeline = collect();

        // Opening Balance (always show as first entry)
        $timeline->push([
            'type' => 'opening_balance',
            'date' => '1900-01-01', // Shows first when sorted
            'reference' => '-',
            'debit' => (float) max(0, $supplier->opening_balance ?? 0),
            'credit' => (float) max(0, -($supplier->opening_balance ?? 0)),
            'description' => 'Opening Balance',
        ]);

        // Shipment Settlements (Debit - له)
        // نستخدم final_supplier_balance - previous_supplier_balance للحصول على صافي هذه الشحنة
        foreach ($shipments as $shipment) {
            $netThisShipment = $shipment->final_supplier_balance - ($shipment->previous_supplier_balance ?? 0);

            $timeline->push([
                'type' => 'settlement',
                'date' => $shipment->settled_at?->format('Y-m-d') ?? $shipment->date->format('Y-m-d'),
                'reference' => $shipment->number,
                'debit' => (float) max(0, $netThisShipment), // له
                'credit' => (float) max(0, -$netThisShipment), // عليه (لو سالب)
                'description' => 'Settlement - Sales: ' . number_format($shipment->total_sales, 2),
            ]);
        }

        // Expenses (Credit - عليه)
        foreach ($expenses as $expense) {
            $timeline->push([
                'type' => 'expense',
                'date' => $expense->date->format('Y-m-d'),
                'reference' => $expense->expense_number,
                'debit' => 0,
                'credit' => (float) $expense->amount,
                'description' => 'Expense: ' . ($expense->description ?? '-'),
            ]);
        }

        // Payments to Supplier (Credit - عليه = دفعنا له)
        foreach ($payments as $payment) {
            $timeline->push([
                'type' => 'payment',
                'date' => $payment->date->format('Y-m-d'),
                'reference' => $payment->expense_number,
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'description' => 'Payment to Supplier: ' . ($payment->description ?? '-'),
            ]);
        }

        // Sort by date and calculate running balance
        $timeline = $timeline->sortBy('date')->values();

        $runningBalance = 0;
        return $timeline->map(function ($item) use (&$runningBalance) {
            $runningBalance += $item['debit'] - $item['credit'];
            $item['balance'] = $runningBalance;
            return $item;
        });
    }

    protected function getServiceName(): string
    {
        return 'SupplierStatementService';
    }
}
