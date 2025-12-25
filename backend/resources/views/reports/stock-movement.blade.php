@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
    $number = fn($num) => number_format($num, 0);
@endphp

@section('title', 'تقرير حركة المخزون')
@section('report-title')
    <span class="ar">تقرير حركة المخزون</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Stock Movement Report</span>
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
    1. MOVEMENT SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص الحركة</span>
            <span class="en">Movement Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $number($summary['total_in']) }} كجم
                </div>
                <div style="color: #48bb78; font-size: 10pt;">وارد</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #e53e3e;">
                    {{ $number($summary['total_out']) }} كجم
                </div>
                <div style="color: #c53030; font-size: 10pt;">صادر</div>
            </div>
            <div>
                <div
                    style="font-size: 18pt; font-weight: bold; color: {{ $summary['net_change'] >= 0 ? '#38a169' : '#e53e3e' }};">
                    {{ $summary['net_change'] >= 0 ? '+' : '' }}{{ $number($summary['net_change']) }} كجم
                </div>
                <div style="color: #718096; font-size: 10pt;">صافي التغيير</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. PRODUCT MOVEMENT
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">حركة المنتجات</span>
            <span class="en">Product Movement</span>
        </div>

        @if(count($products) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المنتج</span></th>
                        <th class="text-center" style="background-color: #c6f6d5;"><span class="ar">وارد</span></th>
                        <th class="text-center" style="background-color: #fed7d7;"><span class="ar">صادر</span></th>
                        <th class="text-center"><span class="ar">صافي</span></th>
                        <th class="text-center"><span class="ar">رصيد</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td><strong>{{ $product['product_name'] }}</strong></td>
                            <td class="text-center positive">{{ $number($product['in_weight']) }}</td>
                            <td class="text-center negative">{{ $number($product['out_weight']) }}</td>
                            <td class="text-center {{ $product['net_change'] >= 0 ? 'positive' : 'negative' }}">
                                {{ $product['net_change'] >= 0 ? '+' : '' }}{{ $number($product['net_change']) }}
                            </td>
                            <td class="text-center"><strong>{{ $number($product['current_stock']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center positive"><strong>{{ $number($summary['total_in']) }}</strong></td>
                        <td class="text-center negative"><strong>{{ $number($summary['total_out']) }}</strong></td>
                        <td class="text-center {{ $summary['net_change'] >= 0 ? 'positive' : 'negative' }}">
                            <strong>{{ $summary['net_change'] >= 0 ? '+' : '' }}{{ $number($summary['net_change']) }}</strong>
                        </td>
                        <td class="text-center"><strong>{{ $number($summary['current_total']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد حركات خلال هذه الفترة</div>
        @endif
    </div>

@endsection