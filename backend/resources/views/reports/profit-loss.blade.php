@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير الأرباح والخسائر')
@section('report-title')
    <span class="ar">تقرير الأرباح والخسائر</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Profit & Loss Report</span>
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
    1. REVENUE SECTION
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">الإيرادات</span>
            <span class="en">Revenue</span>
        </div>

        <div class="info-box">
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">إجمالي المبيعات (الشحنات المُصفاة)</span>
                </span>
                <span class="summary-value money">{{ $currency($revenue['commission']['total_sales']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">نسبة العمولة</span>
                </span>
                <span class="summary-value">{{ $revenue['commission']['commission_rate'] }}%</span>
            </div>
            <div class="summary-row" style="background-color: #c6f6d5; padding: 10px; border-radius: 5px;">
                <span class="summary-label">
                    <strong><span class="ar">إجمالي الإيرادات (العمولة)</span></strong>
                </span>
                <span class="summary-value money positive"><strong>{{ $currency($revenue['total']) }}</strong></span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. EXPENSES SECTION
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">المصروفات</span>
            <span class="en">Expenses</span>
        </div>

        @if(count($expenses['by_category']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>
                            <span class="ar">التصنيف</span>
                            <span class="en">Category</span>
                        </th>
                        <th>
                            <span class="ar">العدد</span>
                            <span class="en">Count</span>
                        </th>
                        <th class="text-left">
                            <span class="ar">المبلغ</span>
                            <span class="en">Amount</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses['by_category'] as $category)
                        <tr>
                            <td>{{ $category['category'] }}</td>
                            <td class="text-center">{{ $category['count'] }}</td>
                            <td class="text-left money negative">{{ $currency($category['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2"><strong>الإجمالي</strong></td>
                        <td class="text-left money negative"><strong>{{ $currency($expenses['total']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد مصروفات في هذه الفترة</div>
        @endif

        <div class="info-box" style="margin-top: 10px;">
            <div class="summary-row">
                <span class="summary-label"><span class="ar">مصروفات نقدية</span></span>
                <span class="summary-value money">{{ $currency($expenses['by_payment_method']['cash'] ?? 0) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">مصروفات بنكية</span></span>
                <span class="summary-value money">{{ $currency($expenses['by_payment_method']['bank'] ?? 0) }}</span>
            </div>
        </div>
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
                <span class="summary-label">
                    <span class="ar">إجمالي الإيرادات</span>
                    <span class="en">Total Revenue</span>
                </span>
                <span class="summary-value money positive">{{ $currency($summary['total_revenue']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">(-) إجمالي المصروفات</span>
                    <span class="en">(-) Total Expenses</span>
                </span>
                <span class="summary-value money negative">-{{ $currency($summary['total_expenses']) }}</span>
            </div>
            <div class="final-total">
                <span class="summary-label">
                    <strong>
                        <span class="ar">صافي الربح</span>
                        <span class="en">Net Profit</span>
                    </strong>
                </span>
                <span class="summary-value" style="color: {{ $summary['net_profit'] >= 0 ? '#48bb78' : '#e53e3e' }}">
                    <strong>{{ $currency($summary['net_profit']) }}</strong>
                </span>
            </div>
        </div>

        <div class="info-box" style="margin-top: 15px; text-align: center;">
            <span class="ar">هامش الربح:</span>
            <strong style="font-size: 16pt; color: {{ $summary['profit_margin'] >= 0 ? '#38a169' : '#e53e3e' }}">
                {{ $summary['profit_margin'] }}%
            </strong>
        </div>
    </div>

@endsection