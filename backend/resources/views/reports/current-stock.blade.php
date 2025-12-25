@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
    $number = fn($num) => number_format($num, 0);
@endphp

@section('title', 'تقرير المخزون الحالي')
@section('report-title')
    <span class="ar">تقرير المخزون الحالي</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Current Stock Report</span>
@endsection
@section('report-date')
    {{ $as_of_date }}
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. STOCK SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص المخزون</span>
            <span class="en">Stock Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 20pt; font-weight: bold; color: #4299e1;">
                    {{ $number($summary['total_products']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">منتج</div>
            </div>
            <div>
                <div style="font-size: 20pt; font-weight: bold; color: #38a169;">
                    {{ $number($summary['total_cartons']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">كرتون</div>
            </div>
            <div>
                <div style="font-size: 20pt; font-weight: bold; color: #ed8936;">
                    {{ $number($summary['total_weight']) }} كجم
                </div>
                <div style="color: #718096; font-size: 10pt;">إجمالي الوزن</div>
            </div>
            <div>
                <div style="font-size: 20pt; font-weight: bold; color: #805ad5;">
                    {{ $number($summary['shipments_count']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">شحنة نشطة</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. PRODUCT DETAILS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">تفاصيل المنتجات</span>
            <span class="en">Product Details</span>
        </div>

        @if(count($products) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المنتج</span></th>
                        <th class="text-center"><span class="ar">الكراتين</span></th>
                        <th class="text-center"><span class="ar">الوزن (كجم)</span></th>
                        <th class="text-center"><span class="ar">الشحنات</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td><strong>{{ $product['product_name'] }}</strong></td>
                            <td class="text-center">{{ $number($product['total_cartons']) }}</td>
                            <td class="text-center">{{ $number($product['total_weight']) }}</td>
                            <td class="text-center">{{ $product['shipments_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center"><strong>{{ $number($summary['total_cartons']) }}</strong></td>
                        <td class="text-center"><strong>{{ $number($summary['total_weight']) }}</strong></td>
                        <td class="text-center"><strong>{{ $number($summary['shipments_count']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا يوجد مخزون حالياً</div>
        @endif
    </div>

@endsection