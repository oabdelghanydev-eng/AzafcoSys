<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Shipment;
use App\Models\Customer;
use App\Services\Reports\DailyClosingReportService;
use App\Services\Reports\ShipmentSettlementReportService;
use App\Services\Reports\PdfGeneratorService;
use App\Traits\ApiResponse;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @tags Report
 */
class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Get daily report for a specific date
     * Permission: reports.daily
     */
    public function daily(Request $request, string $date): JsonResponse
    {
        $this->checkPermission('reports.daily');
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RPT_001',
                    'message' => 'صيغة التاريخ غير صحيحة',
                    'message_en' => 'Invalid date format'
                ]
            ], 422);
        }

        // Get daily sales
        $sales = Invoice::where('date', $date)
            ->where('status', 'active')
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(discount), 0) as total_discount
            ')
            ->first();

        // Get daily collections by payment method
        $collections = Collection::where('date', $date)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN payment_method = "bank" THEN amount ELSE 0 END), 0) as bank_total
            ')
            ->first();

        // Get daily expenses by type and payment method
        $expenses = Expense::where('date', $date)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN payment_method = "bank" THEN amount ELSE 0 END), 0) as bank_total,
                COALESCE(SUM(CASE WHEN type = "supplier" THEN amount ELSE 0 END), 0) as supplier_total,
                COALESCE(SUM(CASE WHEN type = "company" THEN amount ELSE 0 END), 0) as company_total
            ')
            ->first();

        // Cash balance calculation
        $cashIn = (float) $collections->cash_total;
        $cashOut = (float) $expenses->cash_total;
        $netCash = $cashIn - $cashOut;

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'sales' => [
                    'count' => (int) $sales->count,
                    'total' => (float) $sales->total,
                    'discount' => (float) $sales->total_discount,
                ],
                'collections' => [
                    'count' => (int) $collections->count,
                    'total' => (float) $collections->total,
                    'cash' => (float) $collections->cash_total,
                    'bank' => (float) $collections->bank_total,
                ],
                'expenses' => [
                    'count' => (int) $expenses->count,
                    'total' => (float) $expenses->total,
                    'cash' => (float) $expenses->cash_total,
                    'bank' => (float) $expenses->bank_total,
                    'supplier' => (float) $expenses->supplier_total,
                    'company' => (float) $expenses->company_total,
                ],
                'net' => [
                    'cash' => $netCash,
                    'sales_vs_collections' => (float) $sales->total - (float) $collections->total,
                ],
            ]
        ]);
    }

    /**
     * Get shipment settlement report
     * Permission: reports.settlement
     */
    public function shipmentSettlement(Shipment $shipment): JsonResponse
    {
        $this->checkPermission('reports.settlement');
        $shipment->load(['supplier', 'items.product']);

        // Get sales from this shipment
        $salesData = DB::table('invoice_items')
            ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('shipment_items.shipment_id', $shipment->id)
            ->where('invoices.status', 'active')
            ->selectRaw('
                COALESCE(SUM(invoice_items.quantity), 0) as total_quantity,
                COALESCE(SUM(invoice_items.subtotal), 0) as total_sales
            ')
            ->first();

        // Get expenses for this shipment
        $expensesTotal = Expense::where('shipment_id', $shipment->id)->sum('amount');

        // Items breakdown
        $itemsBreakdown = $shipment->items->map(function ($item) {
            return [
                'product' => $item->product->name,
                'initial_quantity' => (float) $item->initial_quantity,
                'sold_quantity' => (float) $item->sold_quantity,
                'remaining_quantity' => (float) $item->remaining_quantity,
                'wastage_quantity' => (float) $item->wastage_quantity,
                'carryover_in' => (float) $item->carryover_in_quantity,
                'carryover_out' => (float) $item->carryover_out_quantity,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'shipment' => [
                    'id' => $shipment->id,
                    'number' => $shipment->number,
                    'date' => $shipment->date->format('Y-m-d'),
                    'status' => $shipment->status,
                    'supplier' => $shipment->supplier->name,
                ],
                'summary' => [
                    'total_sales' => (float) $salesData->total_sales,
                    'total_quantity_sold' => (float) $salesData->total_quantity,
                    'total_expenses' => (float) $expensesTotal,
                    'net_profit' => (float) $salesData->total_sales - (float) $expensesTotal,
                ],
                'items' => $itemsBreakdown,
            ]
        ]);
    }

    /**
     * Get customer statement
     * Permission: reports.customers
     */
    public function customerStatement(Request $request, Customer $customer): JsonResponse
    {
        $this->checkPermission('reports.customers');
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        // Get invoices
        $invoicesQuery = $customer->invoices()
            ->where('status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'invoice_number', 'date', 'total', 'paid_amount', 'balance']);

        // Get collections
        $collectionsQuery = $customer->collections()
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->orderBy('date')
            ->get(['id', 'receipt_number', 'date', 'amount', 'payment_method']);

        // Build timeline
        $timeline = collect();

        foreach ($invoicesQuery as $invoice) {
            $timeline->push([
                'type' => 'invoice',
                'date' => $invoice->date->format('Y-m-d'),
                'reference' => $invoice->invoice_number,
                'debit' => (float) $invoice->total,
                'credit' => 0,
                'description' => 'فاتورة',
            ]);
        }

        foreach ($collectionsQuery as $collection) {
            $timeline->push([
                'type' => 'collection',
                'date' => $collection->date->format('Y-m-d'),
                'reference' => $collection->receipt_number,
                'debit' => 0,
                'credit' => (float) $collection->amount,
                'description' => 'تحصيل ' . ($collection->payment_method === 'cash' ? 'نقدي' : 'بنكي'),
            ]);
        }

        // Sort by date
        $timeline = $timeline->sortBy('date')->values();

        // Calculate running balance
        $runningBalance = 0;
        $timeline = $timeline->map(function ($item) use (&$runningBalance) {
            $runningBalance += $item['debit'] - $item['credit'];
            $item['balance'] = $runningBalance;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'current_balance' => (float) $customer->balance,
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
                'summary' => [
                    'total_invoices' => $invoicesQuery->sum('total'),
                    'total_collections' => $collectionsQuery->sum('amount'),
                    'invoices_count' => $invoicesQuery->count(),
                    'collections_count' => $collectionsQuery->count(),
                ],
                'transactions' => $timeline,
            ]
        ]);
    }

    /**
     * Download daily closing report as PDF
     */
    public function dailyPdf(
        Request $request,
        string $date,
        DailyClosingReportService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RPT_001',
                    'message' => 'Invalid date format'
                ]
            ], 422);
        }

        try {
            $data = $reportService->generate($date);

            return $pdfService->download(
                'reports.daily-closing',
                $data,
                'daily-report-' . $date
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RPT_003',
                    'message' => 'PDF generation failed: ' . $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null
                ]
            ], 500);
        }
    }

    /**
     * Download shipment settlement report as PDF
     */
    public function settlementPdf(
        Request $request,
        Shipment $shipment,
        ShipmentSettlementReportService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        // Check if shipment is settled or being settled
        if (!in_array($shipment->status, ['closed', 'settled'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RPT_002',
                    'message' => 'Shipment must be closed or settled to generate report'
                ]
            ], 422);
        }

        try {
            $data = $reportService->generate($shipment);

            return $pdfService->download(
                'reports.shipment-settlement',
                $data,
                'settlement-' . $shipment->number
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RPT_003',
                    'message' => 'PDF generation failed: ' . $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null
                ]
            ], 500);
        }
    }
}
