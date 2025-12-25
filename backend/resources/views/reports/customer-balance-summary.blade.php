@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'ملخص أرصدة العملاء')
@section('report-title')
    <span class="ar">ملخص أرصدة العملاء</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Customer Balances Summary</span>
@endsection
@section('report-date')
    {{ $as_of_date }}
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. BALANCE SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص الأرصدة</span>
            <span class="en">Balance Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #ed8936;">
                    {{ $currency($totals['total_debtors']) }}
                </div>
                <div style="color: #dd6b20; font-size: 10pt;">مدينون (علينا)</div>
                <div style="color: #718096; font-size: 8pt;">{{ $summary['debtors_count'] }} عميل</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $currency($totals['total_creditors']) }}
                </div>
                <div style="color: #48bb78; font-size: 10pt;">دائنون (لنا)</div>
                <div style="color: #718096; font-size: 8pt;">{{ $summary['creditors_count'] }} عميل</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: {{ $totals['net_balance'] >= 0 ? '#ed8936' : '#38a169' }};">
                    {{ $currency(abs($totals['net_balance'])) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">
                    {{ $totals['net_balance'] >= 0 ? 'صافي مستحق لنا' : 'صافي علينا' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. CUSTOMER DETAILS
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
                        <th><span class="ar">كود</span></th>
                        <th><span class="ar">العميل</span></th>
                        <th class="text-center"><span class="ar">الحالة</span></th>
                        <th class="text-left"><span class="ar">الرصيد</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td><small>{{ $customer['customer_code'] }}</small></td>
                            <td><strong>{{ $customer['customer_name'] }}</strong></td>
                            <td class="text-center">
                                @if($customer['balance_type'] === 'debtor')
                                    <span style="background-color: #fed7aa; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">مدين</span>
                                @elseif($customer['balance_type'] === 'creditor')
                                    <span style="background-color: #c6f6d5; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">دائن</span>
                                @else
                                    <span style="background-color: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">متوازن</span>
                                @endif
                            </td>
                            <td class="text-left money {{ $customer['balance_type'] === 'debtor' ? 'positive' : ($customer['balance_type'] === 'creditor' ? 'negative' : '') }}">
                                <strong>{{ $currency(abs($customer['balance'])) }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>إجمالي الأرصدة</strong></td>
                        <td class="text-left money">
                            <strong>{{ $currency(abs($totals['net_balance'])) }}</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد أرصدة عملاء</div>
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
                <span class="summary-label"><span class="ar">إجمالي العملاء</span></span>
                <span class="summary-value">{{ $summary['total_customers'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">عملاء مدينون</span></span>
                <span class="summary-value positive">{{ $summary['debtors_count'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">عملاء دائنون</span></span>
                <span class="summary-value negative">{{ $summary['creditors_count'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">عملاء متوازنون</span></span>
                <span class="summary-value">{{ $summary['settled_count'] }}</span>
            </div>
        </div>
    </div>

@endsection
