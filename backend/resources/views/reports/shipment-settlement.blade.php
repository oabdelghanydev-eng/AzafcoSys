@extends('reports.layouts.pdf-layout')

@section('title', 'Shipment Settlement Report - ' . $shipment->number)
@section('report-title', 'Shipment Settlement Report')
@section('report-date', 'Shipment #' . $shipment->number)

@section('content')

    {{-- 1. Basic Info --}}
    <div class="section">
        <div class="section-title">1. Shipment Information</div>
        <table>
            <tr>
                <td><strong>Shipment Number:</strong></td>
                <td>{{ $shipment->number }}</td>
                <td><strong>Supplier:</strong></td>
                <td>{{ $supplier->name }}</td>
            </tr>
            <tr>
                <td><strong>Arrival Date:</strong></td>
                <td>{{ \Carbon\Carbon::parse($arrivalDate)->format('d/m/Y') }}</td>
                <td><strong>Settlement Date:</strong></td>
                <td>{{ \Carbon\Carbon::parse($settlementDate)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>Duration:</strong></td>
                <td colspan="3">{{ $durationDays }} days</td>
            </tr>
        </table>
    </div>

    {{-- 2. Sales by Product --}}
    <div class="section">
        <div class="section-title">2. Sales by Product</div>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Qty Sold</th>
                    <th class="text-right">Weight Sold</th>
                    <th class="text-right">Total Sales</th>
                    <th class="text-right">Avg Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesByProduct as $sale)
                    <tr>
                        <td>{{ $sale->product_name }}</td>
                        <td class="text-right">{{ number_format($sale->quantity, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->weight, 2) }} kg</td>
                        <td class="text-right money">{{ number_format($sale->total, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->avg_price, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Total</strong></td>
                    <td class="text-right"><strong>{{ number_format($totalSoldQuantity, 2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($totalSoldWeight, 2) }} kg</strong></td>
                    <td class="text-right money"><strong>{{ number_format($totalSalesAmount, 2) }}</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- 3. Returns from Previous Shipment --}}
    @if(isset($previousShipmentReturns) && $previousShipmentReturns->count() > 0)
        <div class="section">
            <div class="section-title">3. Returns from Previous Shipment</div>
            <p><em>(Returns that occurred after the previous shipment was closed)</em></p>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Weight</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previousShipmentReturns as $return)
                        <tr>
                            <td>{{ $return->product->name_ar ?? 'N/A' }}</td>
                            <td class="text-right">{{ number_format($return->quantity, 2) }}</td>
                            <td class="text-right">{{ number_format($return->quantity * ($return->weight_per_unit ?? 0), 2) }} kg
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td><strong>Total Returns</strong></td>
                        <td class="text-right"><strong>{{ number_format($totalReturnsQuantity, 2) }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($totalReturnsWeight ?? 0, 2) }} kg</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- 4. Inventory Movement --}}
    <div class="section">
        <div class="section-title">4. Inventory Movement</div>

        <strong>Incoming:</strong>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Weight</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shipment->items as $item)
                    <tr>
                        <td>{{ $item->product->name_ar ?? $item->product->name_en }}</td>
                        <td class="text-right">{{ number_format($item->initial_quantity, 2) }}</td>
                        <td class="text-right">{{ number_format($item->initial_quantity * $item->weight_per_unit, 2) }} kg</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(isset($carryoverOut) && $carryoverOut->count() > 0)
            <strong>Carried Over to Next Shipment:</strong>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Weight</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carryoverOut as $co)
                        <tr>
                            <td>{{ $co->product->name_ar ?? 'N/A' }}</td>
                            <td class="text-right">{{ number_format($co->quantity, 2) }}</td>
                            <td class="text-right">{{ number_format($co->quantity * ($co->weight_per_unit ?? 0), 2) }} kg</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- 5. Weight Difference --}}
    <div class="section">
        <div class="section-title">5. Weight Analysis</div>
        <table>
            <tr>
                <td><strong>Total Weight In:</strong></td>
                <td class="text-right">{{ number_format($totalWeightIn, 2) }} kg</td>
            </tr>
            <tr>
                <td><strong>Total Weight Out:</strong></td>
                <td class="text-right">{{ number_format($totalWeightOut, 2) }} kg</td>
            </tr>
            <tr class="{{ $weightDifference != 0 ? 'highlight' : '' }}">
                <td><strong>Difference:</strong></td>
                <td class="text-right {{ $weightDifference > 0 ? 'positive' : ($weightDifference < 0 ? 'negative' : '') }}">
                    {{ number_format($weightDifference, 2) }} kg
                    @if($weightDifference == 0) ✓ @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- 6. Supplier Expenses --}}
    @if($supplierExpenses->count() > 0)
        <div class="section">
            <div class="section-title">6. Supplier Expenses</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($supplierExpenses as $index => $expense)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($expense->date)->format('d/m') }}</td>
                            <td>{{ $expense->description }}</td>
                            <td class="text-right money">{{ number_format($expense->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3"><strong>Total Supplier Expenses</strong></td>
                        <td class="text-right money"><strong>{{ number_format($totalSupplierExpenses, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- 7. Financial Summary --}}
    <div class="summary-box">
        <h3>7. Supplier Financial Summary</h3>

        <div class="summary-row">
            <span class="summary-label">Total Sales:</span>
            <span class="summary-value money">{{ number_format($totalSales, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">(-) Returns from Previous Shipment:</span>
            <span class="summary-value money negative">-{{ number_format($previousReturnsDeduction, 2) }}</span>
        </div>
        <div class="summary-row" style="border-top: 2px solid #333; padding-top: 10px;">
            <span class="summary-label"><strong>Net Sales:</strong></span>
            <span class="summary-value money"><strong>{{ number_format($netSales, 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">(-) Company Commission
                ({{ config('settings.company_commission_rate', 6) }}%):</span>
            <span class="summary-value money negative">-{{ number_format($companyCommission, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">(-) Supplier Expenses:</span>
            <span class="summary-value money negative">-{{ number_format($supplierExpensesDeduction, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">(+) Previous Balance:</span>
            <span class="summary-value money positive">+{{ number_format($previousBalance, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">(-) Payments to Supplier:</span>
            <span class="summary-value money negative">-{{ number_format($supplierPayments, 2) }}</span>
        </div>

        <div class="final-total">
            <span class="summary-label"><strong>FINAL SUPPLIER BALANCE:</strong></span>
            <span class="summary-value money"
                style="font-size: 16px;"><strong>{{ number_format($finalSupplierBalance, 2) }}</strong></span>
        </div>
    </div>

@endsection