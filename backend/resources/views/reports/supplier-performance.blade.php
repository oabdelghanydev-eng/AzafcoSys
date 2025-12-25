@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
    $number = fn($num) => number_format($num, 1);
@endphp

@section('title', 'تقرير أداء الموردين')
@section('report-title')
    <span class="ar">تقرير أداء الموردين</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Supplier Performance Report</span>
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
    1. PERFORMANCE SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص الأداء</span>
            <span class="en">Performance Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ $summary['total_suppliers'] }}
                </div>
                <div style="color: #718096; font-size: 10pt;">مورد</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #805ad5;">
                    {{ $summary['total_shipments'] }}
                </div>
                <div style="color: #718096; font-size: 10pt;">شحنة</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $currency($summary['total_sales']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">إجمالي المبيعات</div>
            </div>
            <div>
                @php
                    $avgWastage = $summary['avg_wastage_rate'];
                    $wastageColor = $avgWastage <= 2 ? '#38a169' : ($avgWastage <= 5 ? '#d69e2e' : '#e53e3e');
                @endphp
                <div style="font-size: 18pt; font-weight: bold; color: {{ $wastageColor }};">
                    {{ $number($avgWastage) }}%
                </div>
                <div style="color: #718096; font-size: 10pt;">متوسط الهالك</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ number_format($summary['avg_settlement_days'], 0) }} يوم
                </div>
                <div style="color: #718096; font-size: 10pt;">متوسط التسوية</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. SUPPLIER DETAILS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">تفاصيل الموردين</span>
            <span class="en">Supplier Details</span>
        </div>

        @if(count($suppliers) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المورد</span></th>
                        <th class="text-center"><span class="ar">الشحنات</span></th>
                        <th class="text-left"><span class="ar">المبيعات</span></th>
                        <th class="text-center"><span class="ar">الهالك</span></th>
                        <th class="text-center"><span class="ar">التقييم</span></th>
                        <th class="text-center"><span class="ar">أيام التسوية</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        @php
                            $rate = $supplier['wastage_rate'];
                            $rateColor = $rate <= 2 ? '#38a169' : ($rate <= 5 ? '#d69e2e' : '#e53e3e');
                            $rating = $rate <= 2 ? 'ممتاز' : ($rate <= 5 ? 'متوسط' : 'مرتفع');
                            $ratingBg = $rate <= 2 ? '#c6f6d5' : ($rate <= 5 ? '#fefcbf' : '#fed7d7');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $supplier['supplier_name'] }}</strong>
                                <br><small style="color: #718096;">{{ $supplier['supplier_code'] }}</small>
                            </td>
                            <td class="text-center">{{ $supplier['shipments_count'] }}</td>
                            <td class="text-left money"><strong>{{ $currency($supplier['total_sales']) }}</strong></td>
                            <td class="text-center" style="color: {{ $rateColor }};">{{ $number($rate) }}%</td>
                            <td class="text-center">
                                <span
                                    style="background-color: {{ $ratingBg }}; padding: 2px 8px; border-radius: 4px; font-size: 9pt;">
                                    {{ $rating }}
                                </span>
                            </td>
                            <td class="text-center">{{ number_format($supplier['avg_days_to_settle'], 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي/المتوسط</strong></td>
                        <td class="text-center"><strong>{{ $summary['total_shipments'] }}</strong></td>
                        <td class="text-left money"><strong>{{ $currency($summary['total_sales']) }}</strong></td>
                        <td class="text-center" style="color: {{ $wastageColor }};">
                            <strong>{{ $number($summary['avg_wastage_rate']) }}%</strong>
                        </td>
                        <td></td>
                        <td class="text-center"><strong>{{ number_format($summary['avg_settlement_days'], 0) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد بيانات موردين خلال هذه الفترة</div>
        @endif
    </div>

@endsection