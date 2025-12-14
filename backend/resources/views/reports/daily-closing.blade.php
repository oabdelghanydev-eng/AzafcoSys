@extends('reports.layouts.pdf-layout')

@section('title', 'Daily Closing Report - ' . $date)
@section('report-title', 'Daily Closing Report')
@section('report-date', \Carbon\Carbon::parse($date)->format('d F Y'))

@section('content')

    {{-- 1. Invoice Items (Sales) --}}
    <div class="section">
        <div class="section-title">1. Sales Invoices</div>
        @if($invoiceItems->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Wt.</th>
                        <th class="text-right">Total Wt.</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoiceItems as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item['invoice_number'] }}</td>
                            <td>{{ $item['customer_name'] }}</td>
                            <td>{{ $item['product_name'] }}</td>
                            <td class="text-right">{{ number_format($item['quantity'], 2) }}</td>
                            <td class="text-right">{{ number_format($item['weight_per_unit'], 2) }} kg</td>
                            <td class="text-right">{{ number_format($item['total_weight'], 2) }} kg</td>
                            <td class="text-right money">{{ number_format($item['subtotal'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4"><strong>Total</strong></td>
                        <td class="text-right"><strong>{{ number_format($totalQuantity, 2) }}</strong></td>
                        <td></td>
                        <td class="text-right"><strong>{{ number_format($totalWeight, 2) }} kg</strong></td>
                        <td class="text-right money"><strong>{{ number_format($totalSales, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @else
            <p>No sales invoices for this day.</p>
        @endif
    </div>

    {{-- 2. Collections --}}
    <div class="section">
        <div class="section-title">2. Collections</div>
        @if($collections->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt #</th>
                        <th>Customer</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($collections as $index => $collection)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $collection->receipt_number }}</td>
                            <td>{{ $collection->customer->name }}</td>
                            <td class="text-right money">{{ number_format($collection->amount, 2) }}</td>
                            <td>{{ ucfirst($collection->payment_method) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3"><strong>Total</strong></td>
                        <td class="text-right money"><strong>{{ number_format($totalCollections, 2) }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Cash:</strong> {{ number_format($totalCollectionsCash, 2) }} | <strong>Bank:</strong>
                {{ number_format($totalCollectionsBank, 2) }}</p>
        @else
            <p>No collections for this day.</p>
        @endif
    </div>

    {{-- 3. Expenses --}}
    <div class="section">
        <div class="section-title">3. Expenses</div>
        @if($expenses->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $index => $expense)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $expense->description }}</td>
                            <td>{{ ucfirst($expense->type) }}</td>
                            <td class="text-right money">{{ number_format($expense->amount, 2) }}</td>
                            <td>{{ ucfirst($expense->payment_method) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3"><strong>Total</strong></td>
                        <td class="text-right money"><strong>{{ number_format($totalExpenses, 2) }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Company:</strong> {{ number_format($totalExpensesCompany, 2) }} | <strong>Supplier:</strong>
                {{ number_format($totalExpensesSupplier, 2) }}</p>
        @else
            <p>No expenses for this day.</p>
        @endif
    </div>

    {{-- 4. Transfers --}}
    @if($transfers->count() > 0)
        <div class="section">
            <div class="section-title">4. Transfers</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfers as $index => $transfer)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $transfer->fromAccount?->name ?? 'Cashbox' }}</td>
                            <td>{{ $transfer->toAccount?->name ?? 'Bank' }}</td>
                            <td class="text-right money">{{ number_format($transfer->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- 5. New Shipments --}}
    @if($newShipments->count() > 0)
        <div class="section">
            <div class="section-title">5. New Shipments</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Shipment #</th>
                        <th>Supplier</th>
                        <th class="text-right">Items</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($newShipments as $index => $shipment)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $shipment->number }}</td>
                            <td>{{ $shipment->supplier->name }}</td>
                            <td class="text-right">{{ $shipment->items->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Summary Box --}}
    <div class="summary-box">
        <h3>Daily Summary</h3>

        <div class="summary-row">
            <span class="summary-label">Total Sales:</span>
            <span class="summary-value money">{{ number_format($totalSales, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Collections:</span>
            <span class="summary-value money">{{ number_format($totalCollections, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Expenses:</span>
            <span class="summary-value money negative">-{{ number_format($totalExpenses, 2) }}</span>
        </div>

        <hr style="margin: 15px 0;">

        <h3>Balances</h3>
        <div class="summary-row">
            <span class="summary-label">Market Balance (Customer Debts):</span>
            <span class="summary-value money">{{ number_format($marketBalance, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Cashbox Balance:</span>
            <span class="summary-value money">{{ number_format($cashboxBalance, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Bank Balance:</span>
            <span class="summary-value money">{{ number_format($bankBalance, 2) }}</span>
        </div>
    </div>

    {{-- Remaining Stock --}}
    @if($remainingStock->count() > 0)
        <div class="section" style="margin-top: 20px;">
            <div class="section-title">Remaining Inventory</div>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Weight</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($remainingStock as $stock)
                        <tr>
                            <td>{{ $stock->product->name_ar ?? $stock->product->name_en }}</td>
                            <td class="text-right">{{ number_format($stock->total_quantity, 2) }}</td>
                            <td class="text-right">{{ number_format($stock->total_weight, 2) }} kg</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection