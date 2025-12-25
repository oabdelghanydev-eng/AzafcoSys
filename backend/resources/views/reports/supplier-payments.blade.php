@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير مدفوعات الموردين')
@section('report-title')
    <span class="ar">تقرير مدفوعات الموردين</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Supplier Payments Report</span>
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
    1. PAYMENTS SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص المدفوعات</span>
            <span class="en">Payments Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ $summary['suppliers_count'] }}
                </div>
                <div style="color: #718096; font-size: 10pt;">مورد</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ $currency($totals['total_payments']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">مدفوعات مباشرة</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #ed8936;">
                    {{ $currency($totals['total_expenses']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">مصروفات</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #805ad5;">
                    {{ $currency($totals['grand_total']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">الإجمالي العام</div>
                <div style="color: #718096; font-size: 8pt;">{{ $summary['transactions_count'] }} معاملة</div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. SUPPLIER DETAILS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">تفاصيل المدفوعات حسب المورد</span>
            <span class="en">Payment Details by Supplier</span>
        </div>

        @if(count($suppliers) > 0)
            <table>
                <thead>
                    <tr>
                        <th><span class="ar">المورد</span></th>
                        <th class="text-center"><span class="ar">المعاملات</span></th>
                        <th class="text-left" style="background-color: #ebf8ff;"><span class="ar">مدفوعات</span></th>
                        <th class="text-left" style="background-color: #fefcbf;"><span class="ar">مصروفات</span></th>
                        <th class="text-left"><span class="ar">الإجمالي</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        <tr>
                            <td>
                                <strong>{{ $supplier['supplier_name'] }}</strong>
                                <br><small style="color: #718096;">{{ $supplier['supplier_code'] }}</small>
                            </td>
                            <td class="text-center">
                                <span style="background-color: #e2e8f0; padding: 2px 8px; border-radius: 4px;">
                                    {{ $supplier['transactions_count'] }}
                                </span>
                            </td>
                            <td class="text-left money" style="color: #3182ce;">
                                @if($supplier['payments'] > 0)
                                    {{ $currency($supplier['payments']) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-left money" style="color: #dd6b20;">
                                @if($supplier['expenses'] > 0)
                                    {{ $currency($supplier['expenses']) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-left money"><strong>{{ $currency($supplier['total']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center"><strong>{{ $summary['transactions_count'] }}</strong></td>
                        <td class="text-left money" style="color: #3182ce;">
                            <strong>{{ $currency($totals['total_payments']) }}</strong>
                        </td>
                        <td class="text-left money" style="color: #dd6b20;">
                            <strong>{{ $currency($totals['total_expenses']) }}</strong>
                        </td>
                        <td class="text-left money"><strong>{{ $currency($totals['grand_total']) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد مدفوعات خلال هذه الفترة</div>
        @endif
    </div>

@endsection