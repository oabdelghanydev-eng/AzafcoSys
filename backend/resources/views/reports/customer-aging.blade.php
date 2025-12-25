@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير أعمار الديون')
@section('report-title')
    <span class="ar">تقرير أعمار الديون</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Customer Aging Report</span>
@endsection
@section('report-date')
    {{ $as_of_date }}
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. AGING SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص أعمار الديون</span>
            <span class="en">Aging Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $currency($totals['current']) }}
                </div>
                <div style="color: #48bb78; font-size: 10pt;">0-30 يوم</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #ecc94b;">
                    {{ $currency($totals['days_31_60']) }}
                </div>
                <div style="color: #d69e2e; font-size: 10pt;">31-60 يوم</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #ed8936;">
                    {{ $currency($totals['days_61_90']) }}
                </div>
                <div style="color: #dd6b20; font-size: 10pt;">61-90 يوم</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #e53e3e;">
                    {{ $currency($totals['over_90']) }}
                </div>
                <div style="color: #c53030; font-size: 10pt;">90+ يوم</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. CUSTOMERS DETAILS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">تفاصيل العملاء</span>
            <span class="en">Customer Details</span>
        </div>

        @if(count($customers) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">العميل</span></th>
                        <th class="text-center" style="background-color: #c6f6d5;"><span class="ar">0-30</span></th>
                        <th class="text-center" style="background-color: #fefcbf;"><span class="ar">31-60</span></th>
                        <th class="text-center" style="background-color: #fed7aa;"><span class="ar">61-90</span></th>
                        <th class="text-center" style="background-color: #fed7d7;"><span class="ar">90+</span></th>
                        <th class="text-left"><span class="ar">الإجمالي</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td>
                                <strong>{{ $customer['customer_name'] }}</strong>
                                <br><small style="color: #718096;">{{ $customer['customer_code'] }}</small>
                            </td>
                            <td class="text-center money positive">
                                @if($customer['aging']['current'] > 0)
                                    {{ $currency($customer['aging']['current']) }}
                                @endif
                            </td>
                            <td class="text-center money" style="color: #d69e2e;">
                                @if($customer['aging']['days_31_60'] > 0)
                                    {{ $currency($customer['aging']['days_31_60']) }}
                                @endif
                            </td>
                            <td class="text-center money" style="color: #dd6b20;">
                                @if($customer['aging']['days_61_90'] > 0)
                                    {{ $currency($customer['aging']['days_61_90']) }}
                                @endif
                            </td>
                            <td class="text-center money negative">
                                @if($customer['aging']['over_90'] > 0)
                                    {{ $currency($customer['aging']['over_90']) }}
                                @endif
                            </td>
                            <td class="text-left money"><strong>{{ $currency($customer['total_balance']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center money positive"><strong>{{ $currency($totals['current']) }}</strong></td>
                        <td class="text-center money" style="color: #d69e2e;">
                            <strong>{{ $currency($totals['days_31_60']) }}</strong></td>
                        <td class="text-center money" style="color: #dd6b20;">
                            <strong>{{ $currency($totals['days_61_90']) }}</strong></td>
                        <td class="text-center money negative"><strong>{{ $currency($totals['over_90']) }}</strong></td>
                        <td class="text-left money"><strong>{{ $currency($totals['total']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد ديون مستحقة</div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">3</span>
            <span class="ar">الملخص</span>
            <span class="en">Summary</span>
        </div>

        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label"><span class="ar">عدد العملاء المدينين</span></span>
                <span class="summary-value">{{ $summary['total_customers'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">إجمالي الديون</span></span>
                <span class="summary-value money">{{ $currency($summary['total_debt']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">نسبة الديون الجارية (0-30)</span></span>
                <span class="summary-value positive">{{ $summary['current_percentage'] }}%</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">نسبة الديون المتأخرة (30+)</span></span>
                <span class="summary-value negative">{{ $summary['overdue_percentage'] }}%</span>
            </div>
        </div>
    </div>

@endsection