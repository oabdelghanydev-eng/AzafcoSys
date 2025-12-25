<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Services\Reports\CustomerStatementService;
use App\Services\Reports\DailyClosingReportService;
use App\Services\Reports\DailyReportQueryService;
use App\Services\Reports\PdfGeneratorService;
use App\Services\Reports\ShipmentSettlementReportService;
use App\Services\Reports\SupplierStatementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ReportController
 *
 * Handles report generation with business logic delegated to services.
 */
/**
 * @tags Report
 */
class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DailyReportQueryService $dailyQueryService,
        private CustomerStatementService $customerStatementService,
        private SupplierStatementService $supplierStatementService
    ) {
    }

    /**
     * Get daily report for a specific date
     * Permission: reports.daily
     */
    public function daily(Request $request, string $date): JsonResponse
    {
        $this->checkPermission('reports.daily');

        $this->validateDateFormat($date);

        $data = $this->dailyQueryService->getDailySummary($date);

        return $this->success($data);
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
        $itemsBreakdown = $shipment->items->map(fn($item) => [
            'product' => $item->product->name,
            'cartons' => $item->cartons,
            'sold_cartons' => $item->sold_cartons,
            'remaining_cartons' => $item->remaining_cartons,
            'wastage_quantity' => (float) $item->wastage_quantity,
            'carryover_in_cartons' => $item->carryover_in_cartons,
            'carryover_out_cartons' => $item->carryover_out_cartons,
        ]);

        return $this->success([
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
        ]);
    }

    /**
     * Get customer statement
     * Permission: reports.customers
     */
    public function customerStatement(Request $request, Customer $customer): JsonResponse
    {
        $this->checkPermission('reports.customers');

        $data = $this->customerStatementService->generateStatement(
            $customer,
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get supplier statement
     * Permission: reports.suppliers
     */
    public function supplierStatement(Request $request, Supplier $supplier): JsonResponse
    {
        $this->checkPermission('reports.suppliers');

        $data = $this->supplierStatementService->generateStatement(
            $supplier,
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Profit & Loss report
     * Permission: reports.financial
     */
    public function profitLoss(Request $request, \App\Services\Reports\ProfitLossReportService $service): JsonResponse
    {
        $this->checkPermission('reports.financial');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Cash Flow report
     * Permission: reports.financial
     */
    public function cashFlow(Request $request, \App\Services\Reports\CashFlowReportService $service): JsonResponse
    {
        $this->checkPermission('reports.financial');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Sales by Product report
     * Permission: reports.sales
     */
    public function salesByProduct(Request $request, \App\Services\Reports\SalesByProductService $service): JsonResponse
    {
        $this->checkPermission('reports.sales');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Sales by Customer report
     * Permission: reports.sales
     */
    public function salesByCustomer(Request $request, \App\Services\Reports\SalesByCustomerService $service): JsonResponse
    {
        $this->checkPermission('reports.sales');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Customer Aging report
     * Permission: reports.customers
     */
    public function customerAging(\App\Services\Reports\CustomerAgingService $service): JsonResponse
    {
        $this->checkPermission('reports.customers');

        $data = $service->generate();

        return $this->success($data);
    }

    /**
     * Get Customer Balance Summary report
     * Permission: reports.customers
     */
    public function customerBalances(\App\Services\Reports\CustomerBalanceSummaryService $service): JsonResponse
    {
        $this->checkPermission('reports.customers');

        $data = $service->generate();

        return $this->success($data);
    }

    /**
     * Get Current Stock report
     * Permission: reports.inventory
     */
    public function currentStock(\App\Services\Reports\CurrentStockService $service): JsonResponse
    {
        $this->checkPermission('reports.inventory');

        $data = $service->generate();

        return $this->success($data);
    }

    /**
     * Get Stock Movement report
     * Permission: reports.inventory
     */
    public function stockMovement(Request $request, \App\Services\Reports\StockMovementService $service): JsonResponse
    {
        $this->checkPermission('reports.inventory');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Wastage report
     * Permission: reports.inventory
     */
    public function wastage(Request $request, \App\Services\Reports\WastageReportService $service): JsonResponse
    {
        $this->checkPermission('reports.inventory');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Supplier Balance Summary report
     * Permission: reports.suppliers
     */
    public function supplierBalances(\App\Services\Reports\SupplierBalanceSummaryService $service): JsonResponse
    {
        $this->checkPermission('reports.suppliers');

        $data = $service->generate();

        return $this->success($data);
    }

    /**
     * Get Supplier Performance report
     * Permission: reports.suppliers
     */
    public function supplierPerformance(Request $request, \App\Services\Reports\SupplierPerformanceService $service): JsonResponse
    {
        $this->checkPermission('reports.suppliers');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
    }

    /**
     * Get Supplier Payments report
     * Permission: reports.suppliers
     */
    public function supplierPayments(Request $request, \App\Services\Reports\SupplierPaymentsService $service): JsonResponse
    {
        $this->checkPermission('reports.suppliers');

        $data = $service->generate(
            $request->date_from,
            $request->date_to
        );

        return $this->success($data);
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
        $this->validateDateFormat($date);

        try {
            $data = $reportService->generate($date);

            return $pdfService->download(
                'reports.daily-closing',
                $data,
                'daily-report-' . $date
            );
        } catch (\Exception $e) {
            return $this->error(
                'RPT_003',
                'فشل في إنشاء ملف PDF: ' . $e->getMessage(),
                'PDF generation failed: ' . $e->getMessage(),
                500
            );
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
            return $this->error(
                'RPT_002',
                'يجب إغلاق أو تصفية الشحنة أولاً',
                'Shipment must be closed or settled to generate report',
                422
            );
        }

        try {
            $data = $reportService->generate($shipment);

            return $pdfService->download(
                'reports.shipment-settlement',
                $data,
                'settlement-' . $shipment->number
            );
        } catch (\Exception $e) {
            return $this->error(
                'RPT_003',
                'فشل في إنشاء ملف PDF: ' . $e->getMessage(),
                'PDF generation failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Download customer statement as PDF
     */
    public function customerStatementPdf(
        Request $request,
        Customer $customer,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $this->customerStatementService->generateStatement(
                $customer,
                $request->date_from,
                $request->date_to
            );

            return $pdfService->download(
                'reports.customer-statement',
                $data,
                'customer-statement-' . $customer->code
            );
        } catch (\Exception $e) {
            return $this->error(
                'RPT_003',
                'فشل في إنشاء ملف PDF: ' . $e->getMessage(),
                'PDF generation failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Download supplier statement as PDF
     */
    public function supplierStatementPdf(
        Request $request,
        Supplier $supplier,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $this->supplierStatementService->generateStatement(
                $supplier,
                $request->date_from,
                $request->date_to
            );

            return $pdfService->download(
                'reports.supplier-statement',
                $data,
                'supplier-statement-' . $supplier->code
            );
        } catch (\Exception $e) {
            return $this->error(
                'RPT_003',
                'فشل في إنشاء ملف PDF: ' . $e->getMessage(),
                'PDF generation failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Download Profit & Loss report as PDF
     */
    public function profitLossPdf(
        Request $request,
        \App\Services\Reports\ProfitLossReportService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $reportService->generate(
                $request->date_from,
                $request->date_to
            );

            return $pdfService->download(
                'reports.profit-loss',
                $data,
                'profit-loss-report'
            );
        } catch (\Exception $e) {
            return $this->error('RPT_003', 'فشل في إنشاء ملف PDF', 'PDF generation failed', 500);
        }
    }

    /**
     * Download Cash Flow report as PDF
     */
    public function cashFlowPdf(
        Request $request,
        \App\Services\Reports\CashFlowReportService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $reportService->generate(
                $request->date_from,
                $request->date_to
            );

            return $pdfService->download(
                'reports.cash-flow',
                $data,
                'cash-flow-report'
            );
        } catch (\Exception $e) {
            return $this->error('RPT_003', 'فشل في إنشاء ملف PDF', 'PDF generation failed', 500);
        }
    }

    /**
     * Download Customer Aging report as PDF
     */
    public function customerAgingPdf(
        \App\Services\Reports\CustomerAgingService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $reportService->generate();

            return $pdfService->download(
                'reports.customer-aging',
                $data,
                'customer-aging-report'
            );
        } catch (\Exception $e) {
            return $this->error('RPT_003', 'فشل في إنشاء ملف PDF', 'PDF generation failed', 500);
        }
    }

    /**
     * Download Sales by Product report as PDF
     */
    public function salesByProductPdf(
        Request $request,
        \App\Services\Reports\SalesByProductService $reportService,
        PdfGeneratorService $pdfService
    ) {
        $this->checkPermission('reports.export_pdf');

        try {
            $data = $reportService->generate(
                $request->date_from,
                $request->date_to
            );

            return $pdfService->download(
                'reports.sales-by-product',
                $data,
                'sales-by-product-report'
            );
        } catch (\Exception $e) {
            return $this->error('RPT_003', 'فشل في إنشاء ملف PDF', 'PDF generation failed', 500);
        }
    }

    /**
     * Validate date format.
     *
     * @throws BusinessException
     */
    protected function validateDateFormat(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new BusinessException(
                'RPT_001',
                'صيغة التاريخ غير صحيحة',
                'Invalid date format'
            );
        }
    }
}
