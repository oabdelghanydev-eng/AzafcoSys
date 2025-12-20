<?php
/**
 * Generate Settlement PDF using mPDF (with Arabic support)
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Shipment;
use App\Services\Reports\PdfGeneratorService;
use Illuminate\Support\Facades\DB;

$shipment = Shipment::where('status', 'closed')
    ->with(['supplier', 'items.product'])
    ->first();

if (!$shipment) {
    echo "No closed shipments found!\n";
    exit(1);
}

echo "📊 Generating Settlement PDF for: {$shipment->number}\n";

// Calculate sales data
$sales = DB::table('invoice_items')
    ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->join('products', 'shipment_items.product_id', '=', 'products.id')
    ->where('shipment_items.shipment_id', $shipment->id)
    ->where('invoices.status', 'active')
    ->selectRaw('
        products.id as product_id,
        COALESCE(products.name, products.name_en) as product_name,
        SUM(invoice_items.cartons) as quantity,
        SUM(invoice_items.quantity) as weight,
        SUM(invoice_items.subtotal) as total,
        AVG(invoice_items.unit_price) as avg_price
    ')
    ->groupBy('products.id', 'products.name', 'products.name_en')
    ->get();

$totalSales = $sales->sum('total');
$commission = $totalSales * 0.06;
$netSales = $totalSales - $commission;

// Prepare data
$data = [
    'shipment' => $shipment,
    'supplier' => $shipment->supplier,
    'arrivalDate' => $shipment->date,
    'settlementDate' => now()->format('Y-m-d'),
    'durationDays' => $shipment->date->diffInDays(now()),

    'salesByProduct' => $sales,
    'totalSalesAmount' => $totalSales,
    'totalSoldQuantity' => $sales->sum('quantity'),
    'totalSoldWeight' => $sales->sum('weight'),

    'previousShipmentReturns' => collect(),
    'totalReturnsQuantity' => 0,
    'totalReturnsWeight' => 0,
    'totalReturnsValue' => 0,

    'carryoverIn' => collect(),
    'returnsIn' => collect(),
    'carryoverOut' => collect(),

    'totalWeightIn' => $shipment->items->sum(fn($i) => $i->cartons * $i->weight_per_unit),
    'totalWeightOut' => $sales->sum('weight'),
    'weightDifference' => 0,

    'supplierExpenses' => collect(),
    'totalSupplierExpenses' => 0,

    'totalSales' => $totalSales,
    'previousReturnsDeduction' => 0,
    'netSales' => $netSales,
    'companyCommission' => $commission,
    'supplierExpensesDeduction' => 0,
    'previousBalance' => 0,
    'supplierPayments' => 0,
    'finalSupplierBalance' => $netSales - $commission,
];

$data['weightDifference'] = $data['totalWeightIn'] - $data['totalWeightOut'];

// Generate PDF using PdfGeneratorService (mPDF)
$pdfService = app(PdfGeneratorService::class);
$filename = "reports/settlement-{$shipment->number}.pdf";
$path = $pdfService->save('reports.shipment-settlement', $data, $filename);

echo "✅ PDF saved to: $path\n";
echo "\n💰 Summary:\n";
echo "   Total Sales: " . number_format($totalSales, 2) . " AED\n";
echo "   Commission:  " . number_format($commission, 2) . " AED\n";
echo "   Net:         " . number_format($netSales - $commission, 2) . " AED\n";
