@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير المبيعات حسب المنتج')
@section('report-title')
    <span class="ar">تقرير المبيعات حسب المنتج</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Sales by Product Report</span>
@endsection
@section('report-date')
    @if($period['from'] || $period['to'])
        {{ $period['from'] ?? 'البداية' }} - {{ $period['to'] ?? now()->format('Y-m-d') }}
    @else
        {{ now()->format('Y-m-d') }}
    @endif
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. PRODUCTS TABLE
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">المبيعات حسب المنتج</span>
            <span class="en">Sales by Product</span>
        </div>

        @if(count($products) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المنتج</span><span class="en">Product</span></th>
                        <th class="text-center"><span class="ar">الكمية</span><span class="en">Qty</span></th>
                        <th class="text-center"><span class="ar">الوزن</span><span class="en">Weight</span></th>
                        <th class="text-center"><span class="ar">الفواتير</span><span class="en">Invoices</span></th>
                        <th class="text-left"><span class="ar">الإيرادات</span><span class="en">Revenue</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>{{ $product->product_name }}</td>
                            <td class="text-center">{{ number_format($product->quantity) }}</td>
                            <td class="text-center">{{ number_format($product->weight, 2) }} كجم</td>
                            <td class="text-center">{{ $product->invoices_count }}</td>
                            <td class="text-left money">{{ $currency($product->revenue) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center"><strong>{{ number_format($summary['total_quantity']) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($summary['total_weight'], 2) }} كجم</strong></td>
                        <td class="text-center">-</td>
                        <td class="text-left money"><strong>{{ $currency($summary['total_revenue']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد مبيعات في هذه الفترة</div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">الملخص</span>
            <span class="en">Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 24pt; font-weight: bold; color: #667eea;">{{ $summary['total_products'] }}</div>
                <div style="color: #a0aec0; font-size: 11pt;"><span class="ar">عدد المنتجات</span></div>
            </div>
            <div>
                <div style="font-size: 24pt; font-weight: bold; color: #48bb78;">
                    {{ number_format($summary['total_quantity']) }}</div>
                <div style="color: #a0aec0; font-size: 11pt;"><span class="ar">إجمالي الكمية</span></div>
            </div>
            <div>
                <div style="font-size: 24pt; font-weight: bold; color: #ed8936;">{{ $currency($summary['total_revenue']) }}
                </div>
                <div style="color: #a0aec0; font-size: 11pt;"><span class="ar">إجمالي الإيرادات</span></div>
            </div>
        </div>

        <div class="info-box" style="margin-top: 15px; text-align: center; background-color: #ebf8ff;">
            <span class="ar">متوسط سعر الكيلو:</span>
            <strong style="font-size: 18pt; color: #2b6cb0;">
                {{ $currency($summary['avg_price_per_kg']) }}
            </strong>
        </div>
    </div>

@endsection