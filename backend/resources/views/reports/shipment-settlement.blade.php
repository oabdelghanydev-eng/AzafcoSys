@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', $L('shipment_settlement_report') . ' - ' . $shipment->number)
@section('report-title')
    <span class="ar">تقرير تسوية الشحنة</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Shipment Settlement Report</span>
@endsection
@section('report-date')
    <span class="ar">شحنة رقم</span> {{ $shipment->number }}
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. SHIPMENT INFORMATION / بيانات الشحنة
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">بيانات الشحنة</span>
            <span class="en">Shipment Information</span>
        </div>

        <div class="info-box">
            <div class="info-row">
                <div class="info-label">رقم الشحنة / Shipment Number</div>
                <div class="info-value">{{ $shipment->number }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">المورد / Supplier</div>
                <div class="info-value">{{ $supplier->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">تاريخ الوصول / Arrival Date</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($arrivalDate)->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">تاريخ التسوية / Settlement Date</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($settlementDate)->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">المدة / Duration</div>
                <div class="info-value">{{ $durationDays }} <span class="ar">يوم</span> <span class="en">days</span></div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. SALES BY PRODUCT / المبيعات حسب المنتج
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">المبيعات حسب المنتج</span>
            <span class="en">Sales by Product</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>
                        <span class="ar">المنتج</span>
                        <span class="en">Product</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الكمية المباعة</span>
                        <span class="en">Qty Sold</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الوزن المباع</span>
                        <span class="en">Weight Sold</span>
                    </th>
                    <th class="text-left">
                        <span class="ar">إجمالي المبيعات</span>
                        <span class="en">Total Sales</span>
                    </th>
                    <th class="text-left">
                        <span class="ar">متوسط السعر</span>
                        <span class="en">Avg Price</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesByProduct as $sale)
                    <tr>
                        <td>{{ $sale->product_name }}</td>
                        <td class="text-center">{{ number_format($sale->quantity, 2) }}</td>
                        <td class="text-center">{{ number_format($sale->weight, 2) }} kg</td>
                        <td class="text-left money">{{ $currency($sale->total) }}</td>
                        <td class="text-left">{{ $currency($sale->avg_price) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>
                        <strong>
                            <span class="ar">الإجمالي</span>
                            <span class="en" style="color: #718096;">Total</span>
                        </strong>
                    </td>
                    <td class="text-center"><strong>{{ number_format($totalSoldQuantity, 2) }}</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalSoldWeight, 2) }} kg</strong></td>
                    <td class="text-left money"><strong>{{ $currency($totalSalesAmount) }}</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. RETURNS FROM PREVIOUS SHIPMENT / مرتجعات الشحنة السابقة
    ═══════════════════════════════════════════════════════════════════ --}}
    @if(isset($previousShipmentReturns) && $previousShipmentReturns->count() > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">3</span>
                <span class="ar">مرتجعات الشحنة السابقة</span>
                <span class="en">Returns from Previous Shipment</span>
            </div>
            <p style="color: #718096; font-style: italic; margin-bottom: 10px;">
                <span class="ar">(المرتجعات التي حدثت بعد إغلاق الشحنة السابقة)</span>
                <span class="en">(Returns that occurred after the previous shipment was closed)</span>
            </p>
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">المنتج</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">الكمية</span>
                            <span class="en">Quantity</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">الوزن</span>
                            <span class="en">Weight</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previousShipmentReturns as $return)
                        <tr>
                            <td>{{ $return->product->bilingual_name }}</td>
                            <td class="text-center">{{ number_format($return->cartons, 0) }}</td>
                            <td class="text-center">
                                {{ number_format($return->cartons * ($return->fromShipmentItem->weight_per_unit ?? 0), 2) }} kg
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>
                            <strong>
                                <span class="ar">إجمالي المرتجعات</span>
                                <span class="en" style="color: #718096;">Total Returns</span>
                            </strong>
                        </td>
                        <td class="text-center"><strong>{{ number_format($totalReturnsQuantity, 2) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalReturnsWeight ?? 0, 2) }} kg</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    4. INVENTORY MOVEMENT / حركة المخزون
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">4</span>
            <span class="ar">حركة المخزون</span>
            <span class="en">Inventory Movement</span>
        </div>

        {{-- Incoming Items (Original Shipment Cartons) --}}
        @if(isset($incomingItems) && $incomingItems->count() > 0)
            <p style="font-weight: bold; margin-bottom: 8px;">
                <span class="ar">الوارد من الشحنة (الأصلي):</span>
                <span class="en" style="color: #718096;">Incoming (Original Shipment):</span>
            </p>
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">الصنف</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">عدد الكراتين</span>
                            <span class="en">Cartons</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">وزن الوحدة</span>
                            <span class="en">Unit Weight</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($incomingItems as $item)
                        <tr>
                            <td>{{ $item->product->bilingual_name }}</td>
                            <td class="text-center">{{ number_format($item->cartons, 0) }}</td>
                            <td class="text-center">{{ number_format($item->weight_per_unit ?? 0, 2) }} kg</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td><strong><span class="ar">إجمالي الوارد</span> <span class="en" style="color: #718096;">Total
                                    Incoming</span></strong></td>
                        <td class="text-center"><strong>{{ number_format($totalIncomingCartons ?? 0, 0) }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        @endif

        {{-- Carryover Out (Transferred to Next Shipment) --}}
        @if(isset($carryoverOut) && $carryoverOut->count() > 0)
            <p style="font-weight: bold; margin-bottom: 8px;">
                <span class="ar">الكمية المرحلة للشحنة التالية:</span>
                <span class="en" style="color: #718096;">Carried Over to Next Shipment:</span>
            </p>
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">الصنف</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">عدد الكراتين</span>
                            <span class="en">Cartons</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">وزن الوحدة</span>
                            <span class="en">Unit Weight</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carryoverOut as $co)
                        <tr>
                            <td>{{ $co->product->bilingual_name }}</td>
                            <td class="text-center">{{ number_format($co->cartons, 0) }}</td>
                            <td class="text-center">{{ number_format($co->fromShipmentItem->weight_per_unit ?? 0, 2) }} kg</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #718096; font-style: italic;">
                <span class="ar">لا توجد كمية مرحلة</span>
                <span class="en">No carryover quantity</span>
            </p>
        @endif

        {{-- Carryover In (Received from Previous Shipment) --}}
        @if(isset($carryoverIn) && $carryoverIn->count() > 0)
            <p style="font-weight: bold; margin: 15px 0 8px 0;">
                <span class="ar">الكمية الواردة من الشحنة السابقة:</span>
                <span class="en" style="color: #718096;">Received from Previous Shipment:</span>
            </p>
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">الصنف</span>
                            <span class="en">Product</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">عدد الكراتين</span>
                            <span class="en">Cartons</span>
                        </th>
                        <th class="text-center">
                            <span class="ar">وزن الوحدة</span>
                            <span class="en">Unit Weight</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carryoverIn as $ci)
                        <tr>
                            <td>{{ $ci->product->bilingual_name }}</td>
                            <td class="text-center">{{ number_format($ci->cartons, 0) }}</td>
                            <td class="text-center">{{ number_format($ci->fromShipmentItem->weight_per_unit ?? 0, 2) }} kg</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    5. WEIGHT ANALYSIS / تحليل الوزن
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">5</span>
            <span class="ar">تحليل الوزن</span>
            <span class="en">Weight Analysis</span>
        </div>

        {{-- Per-Product Weight Analysis Table --}}
        <table>
            <thead>
                <tr>
                    <th>
                        <span class="ar">المنتج</span>
                        <span class="en">Product</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الوزن الوارد</span>
                        <span class="en">Weight In</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الوزن المرحل</span>
                        <span class="en">Carryover Out</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الوزن الفعلي</span>
                        <span class="en">Effective</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الوزن المباع</span>
                        <span class="en">Weight Sold</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">الهالك</span>
                        <span class="en">Wastage</span>
                    </th>
                    <th class="text-center">
                        <span class="ar">نسبة %</span>
                        <span class="en">%</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($shipment->items as $item)
                    @php
                        // الوزن الوارد من الشحنة الحالية
                        $originalWeight = $item->cartons * $item->weight_per_unit;

                        // الوزن المرحل للداخل (من الشحنة السابقة - بالوزن الأصلي)
                        // نحصل عليه من سجلات الترحيل مع الوزن الأصلي
                        $carryoverInWeight = $carryoverIn->where('product_id', $item->product_id)->sum(function ($co) {
                            return $co->cartons * ($co->fromShipmentItem?->weight_per_unit ?? 0);
                        });

                        // الوزن الوارد الكلي = الأصلي + المرحل للداخل (كل بوزنه)
                        $weightIn = $originalWeight + $carryoverInWeight;

                        // الوزن المرحل للخارج (من سجلات الترحيل بالوزن الفعلي)
                        $carryoverOutWeight = $carryoverOut->where('product_id', $item->product_id)->sum(function ($co) {
                            return $co->cartons * ($co->fromShipmentItem?->weight_per_unit ?? 0);
                        });

                        // الوزن الوارد الفعلي = الوارد - المرحل للخارج
                        $effectiveWeightIn = $weightIn - $carryoverOutWeight;

                        // الوزن المباع
                        $saleData = $salesByProduct->where('product_id', $item->product_id)->first();
                        $weightOut = $saleData ? ($saleData->weight ?? 0) : 0;

                        // الهالك = الفعلي - المباع
                        $wastage = $effectiveWeightIn - $weightOut;
                        $wastagePercent = $effectiveWeightIn > 0 ? ($wastage / $effectiveWeightIn) * 100 : 0;
                    @endphp
                    <tr>
                        <td>{{ $item->product->bilingual_name }}</td>
                        <td class="text-center">{{ number_format($weightIn, 2) }}</td>
                        <td class="text-center">{{ number_format($carryoverOutWeight, 2) }}</td>
                        <td class="text-center"><strong>{{ number_format($effectiveWeightIn, 2) }}</strong></td>
                        <td class="text-center">{{ number_format($weightOut, 2) }}</td>
                        <td class="text-center {{ $wastage > 0 ? 'negative' : ($wastage < 0 ? 'positive' : '') }}">
                            {{ number_format($wastage, 2) }}
                        </td>
                        <td
                            class="text-center {{ $wastagePercent > 5 ? 'negative' : ($wastagePercent > 0 ? 'highlight' : '') }}">
                            {{ number_format($wastagePercent, 1) }}%
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>
                        <strong>
                            <span class="ar">الإجمالي</span>
                            <span class="en" style="color: #718096;">Total</span>
                        </strong>
                    </td>
                    <td class="text-center"><strong>{{ number_format($totalWeightIn, 2) }} kg</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalCarryoverOutWeight ?? 0, 2) }} kg</strong></td>
                    <td class="text-center"><strong>{{ number_format($effectiveWeightIn ?? 0, 2) }} kg</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalWeightOut, 2) }} kg</strong></td>
                    <td
                        class="text-center {{ $weightDifference > 0 ? 'negative' : ($weightDifference < 0 ? 'positive' : '') }}">
                        <strong>{{ number_format($weightDifference, 2) }} kg</strong>
                        @if($weightDifference == 0)
                            <span class="badge badge-success">✓</span>
                        @elseif($weightDifference > 0)
                            <span class="badge badge-danger">هالك</span>
                        @endif
                    </td>
                    @php
                        $totalWastagePercent = ($effectiveWeightIn ?? 0) > 0 ? ($weightDifference / ($effectiveWeightIn ?? 1)) * 100 : 0;
                    @endphp
                    <td class="text-center {{ $totalWastagePercent > 5 ? 'negative' : '' }}">
                        <strong>{{ number_format($totalWastagePercent, 1) }}%</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    6. SUPPLIER EXPENSES / مصروفات المورد
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($supplierExpenses->count() > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">6</span>
                <span class="ar">مصروفات المورد</span>
                <span class="en">Supplier Expenses</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>
                            <span class="ar">التاريخ</span>
                            <span class="en">Date</span>
                        </th>
                        <th>
                            <span class="ar">الوصف</span>
                            <span class="en">Description</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($supplierExpenses as $index => $expense)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($expense->date)->format('d/m') }}</td>
                            <td>{{ $expense->description }}</td>
                            <td class="text-left money negative">{{ $currency($expense->amount) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3">
                            <strong>
                                <span class="ar">إجمالي مصروفات المورد</span>
                                <span class="en" style="color: #718096;">Total Supplier Expenses</span>
                            </strong>
                        </td>
                        <td class="text-left money negative"><strong>{{ $currency($totalSupplierExpenses) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    7. FINANCIAL SUMMARY / الملخص المالي للمورد
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="summary-box">
        <h3>
            <span class="ar">7. الملخص المالي للمورد</span>
            <span class="en">Supplier Financial Summary</span>
        </h3>

        {{-- 1. Total Sales --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">إجمالي المبيعات</span>
                <span class="en">Total Sales</span>
            </span>
            <span class="summary-value money">{{ $currency($totalSales) }}</span>
        </div>

        {{-- 2. Returns Deduction --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">(-) خصم مرتجعات الشحنة السابقة</span>
                <span class="en">(-) Returns from Previous Shipment</span>
            </span>
            <span class="summary-value money negative">-{{ $currency($previousReturnsDeduction) }}</span>
        </div>

        {{-- 2.5 Price Adjustments --}}
        @if(isset($priceAdjustments) && $priceAdjustments != 0)
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">{{ $priceAdjustments > 0 ? '(+) تسويات أسعار' : '(-) تسويات أسعار' }}</span>
                    <span class="en">{{ $priceAdjustments > 0 ? '(+) Price Adjustments' : '(-) Price Adjustments' }}</span>
                </span>
                <span class="summary-value money {{ $priceAdjustments > 0 ? 'positive' : 'negative' }}">
                    {{ $priceAdjustments > 0 ? '+' : '' }}{{ $currency($priceAdjustments) }}
                </span>
            </div>
        @endif

        {{-- 3. Commission --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">(-) عمولة الشركة ({{ config('settings.company_commission_rate', 6) }}%)</span>
                <span class="en">(-) Company Commission</span>
            </span>
            <span class="summary-value money negative">-{{ $currency($companyCommission) }}</span>
        </div>

        {{-- 4. Supplier Expenses --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">(-) مصروفات المورد</span>
                <span class="en">(-) Supplier Expenses</span>
            </span>
            <span class="summary-value money negative">-{{ $currency($supplierExpensesDeduction) }}</span>
        </div>

        {{-- 5. Previous Balance --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">(+) الرصيد السابق</span>
                <span class="en">(+) Previous Balance</span>
            </span>
            <span class="summary-value money positive">+{{ $currency($previousBalance) }}</span>
        </div>

        {{-- 6. Payments to Supplier --}}
        <div class="summary-row">
            <span class="summary-label">
                <span class="ar">(-) مدفوعات للمورد</span>
                <span class="en">(-) Payments to Supplier</span>
            </span>
            <span class="summary-value money negative">-{{ $currency($supplierPayments) }}</span>
        </div>

        {{-- FINAL BALANCE --}}
        <div class="final-total">
            <span class="summary-label">
                <strong>
                    <span class="ar">الرصيد النهائي للمورد</span>
                    <span class="en">FINAL SUPPLIER BALANCE</span>
                </strong>
            </span>
            <span class="summary-value money" style="font-size: 16px;">
                <strong>{{ $currency($finalSupplierBalance) }}</strong>
            </span>
        </div>
    </div>

@endsection