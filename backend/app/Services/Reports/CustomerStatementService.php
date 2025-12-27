<?php

namespace App\Services\Reports;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\ReturnModel;
use App\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * CustomerStatementService
 * 
 * Generates customer account statements showing invoices, collections, returns and credit notes.
 * 
 * @package App\Services\Reports
 */
class CustomerStatementService extends BaseService
{
    /**
     * Generate customer statement.
     *
     * @param Customer $customer The customer
     * @param string|null $dateFrom Start date filter
     * @param string|null $dateTo End date filter
     * @return array Statement data
     */
    public function generateStatement(Customer $customer, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $invoices = $this->getInvoices($customer, $dateFrom, $dateTo);
        $collections = $this->getCollections($customer, $dateFrom, $dateTo);
        $returns = $this->getReturns($customer, $dateFrom, $dateTo);
        $creditNotes = $this->getCreditNotes($customer, $dateFrom, $dateTo);

        // حساب الرصيد الافتتاحي للفترة (الحركات قبل تاريخ البداية)
        $periodOpeningBalance = $this->calculatePeriodOpeningBalance($customer, $dateFrom);

        $timeline = $this->buildTimeline($invoices, $collections, $returns, $creditNotes, $periodOpeningBalance);

        $totalReturns = $returns->sum('total_amount');
        $totalCreditNotes = $creditNotes->where('type', 'credit')->sum('amount');
        $totalDebitNotes = $creditNotes->where('type', 'debit')->sum('amount');

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'opening_balance' => (float) $customer->opening_balance,
                'current_balance' => (float) $customer->balance,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'opening_balance' => $periodOpeningBalance,
            ],
            'summary' => [
                'opening_balance' => (float) $periodOpeningBalance,
                'total_invoices' => (float) $invoices->sum('total'),
                'total_collections' => (float) $collections->sum('amount'),
                'total_returns' => (float) $totalReturns,
                'total_credit_notes' => (float) $totalCreditNotes,
                'total_debit_notes' => (float) $totalDebitNotes,
                'closing_balance' => (float) ($periodOpeningBalance + $invoices->sum('total') + $totalDebitNotes - $collections->sum('amount') - $totalReturns - $totalCreditNotes),
                'invoices_count' => $invoices->count(),
                'collections_count' => $collections->count(),
                'returns_count' => $returns->count(),
            ],
            'transactions' => $timeline,
        ];
    }

    /**
     * Calculate opening balance for a specific period.
     * الرصيد الافتتاحي = رصيد افتتاحي + (الفواتير - التحصيلات - المرتجعات) قبل تاريخ البداية
     */
    protected function calculatePeriodOpeningBalance(Customer $customer, ?string $dateFrom): float
    {
        if (!$dateFrom) {
            return (float) $customer->opening_balance;
        }

        $openingBalance = (float) $customer->opening_balance;

        // الفواتير قبل الفترة
        $invoicesBefore = $customer->invoices()
            ->where('status', 'active')
            ->whereDate('date', '<', $dateFrom)
            ->sum('total');

        // التحصيلات قبل الفترة
        $collectionsBefore = $customer->collections()
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        // المرتجعات قبل الفترة
        $returnsBefore = ReturnModel::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereDate('date', '<', $dateFrom)
            ->sum('total');

        // الإشعارات قبل الفترة
        $creditNotesBefore = CreditNote::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('type', 'credit')
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        $debitNotesBefore = CreditNote::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('type', 'debit')
            ->whereDate('date', '<', $dateFrom)
            ->sum('amount');

        return $openingBalance + $invoicesBefore - $collectionsBefore - $returnsBefore - $creditNotesBefore + $debitNotesBefore;
    }

    /**
     * Get customer invoices for period.
     */
    protected function getInvoices(Customer $customer, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $customer->invoices()
            ->where('status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'invoice_number', 'date', 'total', 'paid_amount', 'balance']);
    }

    /**
     * Get customer collections for period.
     */
    protected function getCollections(Customer $customer, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $customer->collections()
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'receipt_number', 'date', 'amount', 'payment_method']);
    }

    /**
     * Get customer returns for period.
     */
    protected function getReturns(Customer $customer, ?string $dateFrom, ?string $dateTo): Collection
    {
        return ReturnModel::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'return_number', 'date', 'total_amount']);
    }

    /**
     * Get customer credit/debit notes for period.
     */
    protected function getCreditNotes(Customer $customer, ?string $dateFrom, ?string $dateTo): Collection
    {
        return CreditNote::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'type', 'date', 'amount', 'reason']);
    }

    /**
     * Build transaction timeline with running balance.
     * 
     * Debit (يزيد الرصيد): Invoice, Debit Note
     * Credit (يقلل الرصيد): Collection, Return, Credit Note
     */
    protected function buildTimeline(
        Collection $invoices,
        Collection $collections,
        Collection $returns,
        Collection $creditNotes,
        float $periodOpeningBalance = 0
    ): Collection {
        $timeline = collect();

        // Opening Balance row (always show as first entry)
        $timeline->push([
            'type' => 'opening_balance',
            'date' => '1900-01-01', // Shows first when sorted
            'reference' => '-',
            'debit' => $periodOpeningBalance > 0 ? $periodOpeningBalance : 0,
            'credit' => $periodOpeningBalance < 0 ? abs($periodOpeningBalance) : 0,
            'description' => 'Opening Balance',
        ]);

        // Invoices (Debit - يزيد رصيد العميل)
        foreach ($invoices as $invoice) {
            $timeline->push([
                'type' => 'invoice',
                'date' => $invoice->date->format('Y-m-d'),
                'reference' => $invoice->invoice_number,
                'debit' => (float) $invoice->total,
                'credit' => 0,
                'description' => 'Invoice',
            ]);
        }

        // Collections (Credit - يقلل رصيد العميل)
        foreach ($collections as $collection) {
            $timeline->push([
                'type' => 'collection',
                'date' => $collection->date->format('Y-m-d'),
                'reference' => $collection->receipt_number,
                'debit' => 0,
                'credit' => (float) $collection->amount,
                'description' => 'Collection (' . ($collection->payment_method === 'cash' ? 'Cash' : 'Bank') . ')',
            ]);
        }

        // Returns (Credit - يقلل رصيد العميل)
        foreach ($returns as $return) {
            $timeline->push([
                'type' => 'return',
                'date' => $return->date->format('Y-m-d'),
                'reference' => $return->return_number,
                'debit' => 0,
                'credit' => (float) $return->total_amount,
                'description' => 'Return',
            ]);
        }

        // Credit Notes (Credit/Debit based on type)
        foreach ($creditNotes as $note) {
            if ($note->type === 'credit') {
                // Credit Note - يقلل رصيد العميل (خصم للعميل)
                $timeline->push([
                    'type' => 'credit_note',
                    'date' => $note->date->format('Y-m-d'),
                    'reference' => 'CN-' . $note->id,
                    'debit' => 0,
                    'credit' => (float) $note->amount,
                    'description' => 'إشعار دائن: ' . ($note->reason ?? ''),
                ]);
            } else {
                // Debit Note - يزيد رصيد العميل (إضافة على العميل)
                $timeline->push([
                    'type' => 'debit_note',
                    'date' => $note->date->format('Y-m-d'),
                    'reference' => 'DN-' . $note->id,
                    'debit' => (float) $note->amount,
                    'credit' => 0,
                    'description' => 'إشعار مدين: ' . ($note->reason ?? ''),
                ]);
            }
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
        return 'CustomerStatementService';
    }
}
