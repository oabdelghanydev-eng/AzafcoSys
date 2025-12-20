@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', $L('daily_closing_report') . ' - ' . $date)
@section('report-title')
    <span class="ar">تقرير الإغلاق اليومي</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Daily Closing Report</span>
@endsection
@section('report-date', \Carbon\Carbon::parse($date)->format('d F Y') . ' | ' . \Carbon\Carbon::parse($date)->locale('ar')->translatedFormat('l d F Y'))

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. SALES INVOICES / فواتير المبيعات
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">فواتير المبيعات</span>
            <span class="en">Sales Invoices</span>
        </div>

        @if($invoiceItems->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>
                            <span class="ar">رقم الفاتورة</span>
                            <span class="en">Invoice #</span>
                        </th>
                        <th>
                            <span class="ar">العميل</span>
                            <span class="en">Customer</span>
                        </th>
                        <th>
                            <span class="ar">المنتج</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">الكمية</span>
                            <span class="en">Qty</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">وزن الوحدة</span>
                            <span class="en">Unit Wt.</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">سعر الكيلو</span>
                            <span class="en">Price/KG</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoiceItems as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $item['invoice_number'] }}</td>
                            <td>{{ $item['customer_name'] }}</td>
                            <td>{{ $item['product_name'] }}</td>
                            <td class="text-center">{{ number_format($item['cartons'], 0) }}</td>
                            <td class="text-center">{{ number_format($item['weight_per_unit'], 2) }} kg</td>
                            <td class="text-center">{{ $currency($item['price']) }}</td>
                            <td class="text-left money">{{ $currency($item['subtotal']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4">
                            <strong>
                                <span class="ar">الإجمالي</span>
                                <span class="en" style="color: #718096;">Total</span>
                            </strong>
                        </td>
                        <td class="text-center"><strong>{{ number_format($totalCartons, 0) }}</strong></td>
                        <td></td>
                        <td></td>
                        <td class="text-left money"><strong>{{ $currency($totalSales) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @else
            <div class="no-data">
                <span class="ar">لا توجد فواتير مبيعات لهذا اليوم</span>
                <br>
                <span class="en">No sales invoices for this day</span>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. COLLECTIONS / التحصيلات
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">التحصيلات</span>
            <span class="en">Collections</span>
        </div>

        @if($collections->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>
                            <span class="ar">رقم الإيصال</span>
                            <span class="en">Receipt #</span>
                        </th>
                        <th>
                            <span class="ar">العميل</span>
                            <span class="en">Customer</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                        <th>
                            <span class="ar">طريقة الدفع</span>
                            <span class="en">Method</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($collections as $index => $collection)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $collection->receipt_number }}</td>
                            <td>{{ $collection->customer->name }}</td>
                            <td class="text-left money">{{ $currency($collection->amount) }}</td>
                            <td>
                                @if($collection->payment_method === 'cash')
                                    <span class="badge badge-success">نقدي Cash</span>
                                @else
                                    <span class="badge badge-info">بنك Bank</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3">
                            <strong>
                                <span class="ar">الإجمالي</span>
                                <span class="en" style="color: #718096;">Total</span>
                            </strong>
                        </td>
                        <td class="text-left money"><strong>{{ $currency($totalCollections) }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            {{-- Collections Summary --}}
            <div class="info-box">
                <div class="info-row">
                    <div class="info-label">نقدي / Cash</div>
                    <div class="info-value positive">{{ $currency($totalCollectionsCash) }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">بنك / Bank</div>
                    <div class="info-value">{{ $currency($totalCollectionsBank) }}</div>
                </div>
            </div>
        @else
            <div class="no-data">
                <span class="ar">لا توجد تحصيلات لهذا اليوم</span>
                <br>
                <span class="en">No collections for this day</span>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. EXPENSES / المصروفات
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">3</span>
            <span class="ar">المصروفات</span>
            <span class="en">Expenses</span>
        </div>

        @if($expenses->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>
                            <span class="ar">الوصف</span>
                            <span class="en">Description</span>
                        </th>
                        <th>
                            <span class="ar">النوع</span>
                            <span class="en">Type</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                        <th>
                            <span class="ar">طريقة الدفع</span>
                            <span class="en">Method</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $index => $expense)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $expense->description }}</td>
                            <td>
                                @if($expense->type === 'company')
                                    <span class="badge badge-info">شركة Company</span>
                                @else
                                    <span class="badge badge-warning">مورد Supplier</span>
                                @endif
                            </td>
                            <td class="text-left money negative">{{ $currency($expense->amount) }}</td>
                            <td>
                                @if($expense->payment_method === 'cash')
                                    <span class="badge badge-success">نقدي Cash</span>
                                @else
                                    <span class="badge badge-info">بنك Bank</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3">
                            <strong>
                                <span class="ar">الإجمالي</span>
                                <span class="en" style="color: #718096;">Total</span>
                            </strong>
                        </td>
                        <td class="text-left money negative"><strong>{{ $currency($totalExpenses) }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            {{-- Expenses Summary --}}
            <div class="info-box">
                <div class="info-row">
                    <div class="info-label">شركة / Company</div>
                    <div class="info-value">{{ $currency($totalExpensesCompany) }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">مورد / Supplier</div>
                    <div class="info-value">{{ $currency($totalExpensesSupplier) }}</div>
                </div>
            </div>
        @else
            <div class="no-data">
                <span class="ar">لا توجد مصروفات لهذا اليوم</span>
                <br>
                <span class="en">No expenses for this day</span>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    4. TRANSFERS / التحويلات
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($transfers->count() > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">4</span>
                <span class="ar">التحويلات</span>
                <span class="en">Transfers</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>
                            <span class="ar">من</span>
                            <span class="en">From</span>
                        </th>
                        <th>
                            <span class="ar">إلى</span>
                            <span class="en">To</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfers as $index => $transfer)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $transfer->fromAccount?->name ?? 'الخزينة Cashbox' }}</td>
                            <td>{{ $transfer->toAccount?->name ?? 'البنك Bank' }}</td>
                            <td class="text-left money">{{ $currency($transfer->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    5. NEW SHIPMENTS / شحنات جديدة
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($newShipments->count() > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">5</span>
                <span class="ar">شحنات جديدة</span>
                <span class="en">New Shipments</span>
            </div>

            @foreach($newShipments as $shipment)
                {{-- Shipment Header (Single Line) --}}
                <div
                    style="margin-bottom: 10px; background: #f8fafc; padding: 8px 15px; border-radius: 4px; border: 1px solid #e2e8f0;">
                    <span style="color: #718096;">Shipment #</span> <strong>{{ $shipment->number }}</strong>
                    <span style="color: #cbd5e0; margin: 0 15px;">|</span>
                    <span style="color: #718096;">Supplier</span> <strong>{{ $shipment->supplier->name }}</strong>
                </div>

                {{-- Shipment Items Table --}}
                <table style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th>
                                <span class="ar">الصنف</span>
                                <span class="en">Product</span>
                            </th>
                            <th class="text-center">
                                <span class="ar">الكراتين</span>
                                <span class="en">Cartons</span>
                            </th>
                            <th class="text-center">
                                <span class="ar">وزن الوحدة</span>
                                <span class="en">Unit Wt.</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shipment->items as $index => $item)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>{{ $item->product->name ?? $item->product->name_en }}</td>
                                <td class="text-center">{{ number_format($item->cartons) }}</td>
                                <td class="text-center">{{ number_format($item->weight_per_unit, 2) }} kg</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2">
                                <strong>
                                    <span class="ar">الإجمالي</span>
                                    <span class="en" style="color: #718096;">Total</span>
                                </strong>
                            </td>
                            <td class="text-center"><strong>{{ number_format($shipment->items->sum('cartons')) }}</strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @endforeach
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    DAILY SUMMARY / ملخص اليوم
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="summary-box">
        <h3>
            <span class="ar">ملخص اليوم</span>
            <span class="en">Daily Summary</span>
        </h3>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">إجمالي المبيعات</span>
                <span class="en">Total Sales</span>
            </span>
            <span class="summary-value money">{{ $currency($totalSales) }}</span>
        </div>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">إجمالي التحصيلات</span>
                <span class="en">Total Collections</span>
            </span>
            <span class="summary-value money positive">{{ $currency($totalCollections) }}</span>
        </div>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">إجمالي المصروفات</span>
                <span class="en">Total Expenses</span>
            </span>
            <span class="summary-value money negative">-{{ $currency($totalExpenses) }}</span>
        </div>

        <hr>

        <h3>
            <span class="ar">الأرصدة</span>
            <span class="en">Balances</span>
        </h3>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">رصيد السوق (ديون العملاء)</span>
                <span class="en">Market Balance (Customer Debts)</span>
            </span>
            <span class="summary-value money">{{ $currency($marketBalance) }}</span>
        </div>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">رصيد الخزينة</span>
                <span class="en">Cashbox Balance</span>
            </span>
            <span class="summary-value money positive">{{ $currency($cashboxBalance) }}</span>
        </div>

        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">رصيد البنك</span>
                <span class="en">Bank Balance</span>
            </span>
            <span class="summary-value money">{{ $currency($bankBalance) }}</span>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    REMAINING INVENTORY / المخزون المتبقي
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($remainingStock->count() > 0)
        <div class="section" style="margin-top: 20px;">
            <div class="section-title">
                <span class="number">📦</span>
                <span class="ar">المخزون المتبقي</span>
                <span class="en">Remaining Inventory</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">المنتج</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">الكراتين</span>
                            <span class="en">Cartons</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">الوزن (كجم)</span>
                            <span class="en">Weight (KG)</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">عجز اليوم</span>
                            <span class="en">Today's Wastage</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($remainingStock as $stock)
                        <tr>
                            <td>{{ $stock->product->name ?? $stock->product->name_en }}</td>
                            <td class="text-center">{{ number_format($stock->remaining_cartons, 0) }}</td>
                            <td class="text-center">{{ number_format($stock->total_weight_kg, 2) }} kg</td>
                            <td
                                class="text-center {{ $stock->daily_wastage > 0 ? 'negative' : ($stock->daily_wastage < 0 ? 'positive' : '') }}">
                                @if($stock->daily_wastage != 0)
                                    {{ $stock->daily_wastage > 0 ? '-' : '+' }}{{ number_format(abs($stock->daily_wastage), 2) }} kg
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($totalWastage != 0)
                        <tr class="total-row">
                            <td colspan="3">
                                <strong>
                                    <span class="ar">إجمالي العجز</span>
                                    <span class="en" style="color: #718096;">Total Wastage</span>
                                </strong>
                            </td>
                            <td class="text-center {{ $totalWastage > 0 ? 'negative' : 'positive' }}">
                                <strong>{{ $totalWastage > 0 ? '-' : '+' }}{{ number_format(abs($totalWastage), 2) }} kg</strong>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

@endsection