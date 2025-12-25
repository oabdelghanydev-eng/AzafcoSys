@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
    $number = fn($num) => number_format($num, 2);
@endphp

@section('title', 'تقرير الهالك')
@section('report-title')
    <span class="ar">تقرير الهالك</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Wastage Report</span>
@endsection
@section('report-date')
    @if($period['from'] && $period['to'])
        {{ $period['from'] }} - {{ $period['to'] }}
    @else
        إجمالي
    @endif
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. WASTAGE SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص الهالك</span>
            <span class="en">Wastage Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ $number($summary['total_received']) }} كجم
                </div>
                <div style="color: #718096; font-size: 10pt;">إجمالي الوارد</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $number($summary['total_sold']) }} كجم
                </div>
                <div style="color: #718096; font-size: 10pt;">إجمالي المباع</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #e53e3e;">
                    {{ $number($summary['total_wastage']) }} كجم
                </div>
                <div style="color: #c53030; font-size: 10pt;">إجمالي الهالك</div>
            </div>
            <div>
                @php
                    $rate = $summary['wastage_rate'];
                    $color = $rate <= 2 ? '#38a169' : ($rate <= 5 ? '#d69e2e' : '#e53e3e');
                @endphp
                <div style="font-size: 18pt; font-weight: bold; color: {{ $color }};">
                    {{ $number($rate) }}%
                </div>
                <div style="color: #718096; font-size: 10pt;">نسبة الهالك</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. WASTAGE BY SHIPMENT
    ═══════════════════════════════════════════════════════════════════ --}}
    @if(isset($by_shipment) && count($by_shipment) > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">2</span>
                <span class="ar">الهالك حسب الشحنة</span>
                <span class="en">Wastage by Shipment</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th><span class="ar">الشحنة</span></th>
                        <th><span class="ar">المورد</span></th>
                        <th class="text-center"><span class="ar">وارد</span></th>
                        <th class="text-center"><span class="ar">مباع</span></th>
                        <th class="text-center"><span class="ar">هالك</span></th>
                        <th class="text-center"><span class="ar">النسبة</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($by_shipment as $shipment)
                        @php
                            $rate = $shipment['wastage_rate'];
                            $color = $rate <= 2 ? '#38a169' : ($rate <= 5 ? '#d69e2e' : '#e53e3e');
                        @endphp
                        <tr>
                            <td><strong>#{{ $shipment['shipment_id'] }}</strong></td>
                            <td>{{ $shipment['supplier_name'] ?? 'غير محدد' }}</td>
                            <td class="text-center">{{ $number($shipment['received']) }}</td>
                            <td class="text-center positive">{{ $number($shipment['sold']) }}</td>
                            <td class="text-center negative">{{ $number($shipment['wastage']) }}</td>
                            <td class="text-center" style="color: {{ $color }};">
                                <strong>{{ $number($rate) }}%</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    3. WASTAGE BY PRODUCT
    ═══════════════════════════════════════════════════════════════════ --}}
    @if(isset($by_product) && count($by_product) > 0)
        <div class="section">
            <div class="section-title">
                <span class="number">3</span>
                <span class="ar">الهالك حسب المنتج</span>
                <span class="en">Wastage by Product</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المنتج</span></th>
                        <th class="text-center"><span class="ar">وارد</span></th>
                        <th class="text-center"><span class="ar">مباع</span></th>
                        <th class="text-center"><span class="ar">هالك</span></th>
                        <th class="text-center"><span class="ar">النسبة</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($by_product as $product)
                        @php
                            $rate = $product['wastage_rate'];
                            $color = $rate <= 2 ? '#38a169' : ($rate <= 5 ? '#d69e2e' : '#e53e3e');
                        @endphp
                        <tr>
                            <td><strong>{{ $product['product_name'] }}</strong></td>
                            <td class="text-center">{{ $number($product['received']) }}</td>
                            <td class="text-center positive">{{ $number($product['sold']) }}</td>
                            <td class="text-center negative">{{ $number($product['wastage']) }}</td>
                            <td class="text-center" style="color: {{ $color }};">
                                <strong>{{ $number($rate) }}%</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center"><strong>{{ $number($summary['total_received']) }}</strong></td>
                        <td class="text-center positive"><strong>{{ $number($summary['total_sold']) }}</strong></td>
                        <td class="text-center negative"><strong>{{ $number($summary['total_wastage']) }}</strong></td>
                        <td class="text-center"
                            style="color: {{ $summary['wastage_rate'] <= 2 ? '#38a169' : ($summary['wastage_rate'] <= 5 ? '#d69e2e' : '#e53e3e') }};">
                            <strong>{{ $number($summary['wastage_rate']) }}%</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

@endsection