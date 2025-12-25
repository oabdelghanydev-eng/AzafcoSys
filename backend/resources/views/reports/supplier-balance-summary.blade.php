@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'ملخص أرصدة الموردين')
@section('report-title')
    <span class="ar">ملخص أرصدة الموردين</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Supplier Balances Summary</span>
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
                <div style="font-size: 18pt; font-weight: bold; color: #e53e3e;">
                    {{ $currency($totals['we_owe_suppliers']) }}
                </div>
                <div style="color: #c53030; font-size: 10pt;">نحن ندين لهم</div>
                <div style="color: #718096; font-size: 8pt;">{{ $summary['suppliers_we_owe'] }} مورد</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $currency($totals['suppliers_owe_us']) }}
                </div>
                <div style="color: #48bb78; font-size: 10pt;">يدينون لنا</div>
                <div style="color: #718096; font-size: 8pt;">{{ $summary['suppliers_owe_us'] }} مورد</div>
            </div>
            <div>
                <div
                    style="font-size: 18pt; font-weight: bold; color: {{ $totals['net_balance'] >= 0 ? '#38a169' : '#e53e3e' }};">
                    {{ $currency(abs($totals['net_balance'])) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">
                    {{ $totals['net_balance'] >= 0 ? 'صافي لنا' : 'صافي علينا' }}
                </div>
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
                        <th><span class="ar">كود</span></th>
                        <th><span class="ar">المورد</span></th>
                        <th class="text-center"><span class="ar">الحالة</span></th>
                        <th class="text-left"><span class="ar">الرصيد</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        <tr>
                            <td><small>{{ $supplier['supplier_code'] }}</small></td>
                            <td><strong>{{ $supplier['supplier_name'] }}</strong></td>
                            <td class="text-center">
                                @if($supplier['balance_type'] === 'we_owe_supplier')
                                    <span style="background-color: #fed7d7; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">نحن
                                        ندين</span>
                                @elseif($supplier['balance_type'] === 'supplier_owes_us')
                                    <span style="background-color: #c6f6d5; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">يدين
                                        لنا</span>
                                @else
                                    <span
                                        style="background-color: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 9pt;">متوازن</span>
                                @endif
                            </td>
                            <td
                                class="text-left money {{ $supplier['balance_type'] === 'we_owe_supplier' ? 'negative' : ($supplier['balance_type'] === 'supplier_owes_us' ? 'positive' : '') }}">
                                <strong>{{ $currency(abs($supplier['balance'])) }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>صافي الرصيد</strong></td>
                        <td class="text-left money {{ $totals['net_balance'] >= 0 ? 'positive' : 'negative' }}">
                            <strong>{{ $currency(abs($totals['net_balance'])) }}</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد أرصدة موردين</div>
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
                <span class="summary-label"><span class="ar">إجمالي الموردين</span></span>
                <span class="summary-value">{{ $summary['total_suppliers'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">موردين نحن ندين لهم</span></span>
                <span class="summary-value negative">{{ $summary['suppliers_we_owe'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">موردين يدينون لنا</span></span>
                <span class="summary-value positive">{{ $summary['suppliers_owe_us'] }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label"><span class="ar">موردين متوازنون</span></span>
                <span class="summary-value">{{ $summary['settled_suppliers'] }}</span>
            </div>
        </div>
    </div>

@endsection