@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير التدفق النقدي')
@section('report-title')
    <span class="ar">تقرير التدفق النقدي</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Cash Flow Report</span>
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
    1. INFLOWS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">التدفقات الداخلة (التحصيلات)</span>
            <span class="en">Cash Inflows</span>
        </div>

        <div class="info-box">
            <div class="summary-row">
                <span class="summary-label"><span class="ar">تحصيلات نقدية</span></span>
                <span class="summary-value money positive">{{ $currency($inflows['by_payment_method']['cash']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">تحصيلات بنكية</span></span>
                <span class="summary-value money positive">{{ $currency($inflows['by_payment_method']['bank']) }}</span>
            </div>
            <div class="summary-row" style="background-color: #c6f6d5; padding: 10px; border-radius: 5px;">
                <span class="summary-label"><strong><span class="ar">إجمالي التدفقات الداخلة</span></strong></span>
                <span class="summary-value money positive"><strong>{{ $currency($inflows['total']) }}</strong></span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. OUTFLOWS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">التدفقات الخارجة (المصروفات)</span>
            <span class="en">Cash Outflows</span>
        </div>

        <div class="info-box">
            <div class="summary-row">
                <span class="summary-label"><span class="ar">مصروفات الشركة</span></span>
                <span class="summary-value money negative">{{ $currency($outflows['by_type']['company_expenses']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">مصروفات الموردين</span></span>
                <span class="summary-value money negative">{{ $currency($outflows['by_type']['supplier_expenses']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">مدفوعات للموردين</span></span>
                <span class="summary-value money negative">{{ $currency($outflows['by_type']['supplier_payments']) }}</span>
            </div>
            <div class="summary-row" style="background-color: #fed7d7; padding: 10px; border-radius: 5px;">
                <span class="summary-label"><strong><span class="ar">إجمالي التدفقات الخارجة</span></strong></span>
                <span class="summary-value money negative"><strong>{{ $currency($outflows['total']) }}</strong></span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. ACCOUNT BALANCES
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">3</span>
            <span class="ar">أرصدة الحسابات</span>
            <span class="en">Account Balances</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 24pt; font-weight: bold; color: #38a169;">
                    {{ $currency($account_balances['cashbox']) }}
                </div>
                <div style="color: #a0aec0; font-size: 11pt;">
                    <span class="ar">الخزنة</span>
                    <span class="en">Cashbox</span>
                </div>
            </div>
            <div>
                <div style="font-size: 24pt; font-weight: bold; color: #3182ce;">
                    {{ $currency($account_balances['bank']) }}
                </div>
                <div style="color: #a0aec0; font-size: 11pt;">
                    <span class="ar">البنك</span>
                    <span class="en">Bank</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    4. SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">4</span>
            <span class="ar">الملخص</span>
            <span class="en">Summary</span>
        </div>

        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">إجمالي التدفقات الداخلة</span>
                </span>
                <span class="summary-value money positive">+{{ $currency($summary['total_inflows']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">(-) إجمالي التدفقات الخارجة</span>
                </span>
                <span class="summary-value money negative">-{{ $currency($summary['total_outflows']) }}</span>
            </div>
            <div class="final-total">
                <span class="summary-label">
                    <strong>
                        <span class="ar">صافي التدفق النقدي</span>
                        <span class="en">Net Cash Flow</span>
                    </strong>
                </span>
                <span class="summary-value" style="color: {{ $summary['net_flow'] >= 0 ? '#48bb78' : '#e53e3e' }}">
                    <strong>{{ $currency($summary['net_flow']) }}</strong>
                </span>
            </div>
        </div>

        <div class="info-box" style="margin-top: 15px; text-align: center; background-color: #ebf8ff;">
            <span class="ar">إجمالي السيولة:</span>
            <strong style="font-size: 18pt; color: #2b6cb0;">
                {{ $currency($summary['total_liquidity']) }}
            </strong>
        </div>
    </div>

@endsection