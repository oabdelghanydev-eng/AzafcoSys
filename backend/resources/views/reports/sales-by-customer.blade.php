@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'تقرير المبيعات حسب العميل')
@section('report-title')
    <span class="ar">تقرير المبيعات حسب العميل</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Sales by Customer Report</span>
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
    1. SALES SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">ملخص المبيعات</span>
            <span class="en">Sales Summary</span>
        </div>

        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #4299e1;">
                    {{ $summary['total_customers'] }}
                </div>
                <div style="color: #718096; font-size: 10pt;">عميل</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #805ad5;">
                    {{ $summary['total_invoices'] }}
                </div>
                <div style="color: #718096; font-size: 10pt;">فاتورة</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #38a169;">
                    {{ $currency($summary['total_sales']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">إجمالي المبيعات</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #48bb78;">
                    {{ $currency($summary['total_collected']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">المحصل</div>
            </div>
            <div>
                <div style="font-size: 18pt; font-weight: bold; color: #ed8936;">
                    {{ $currency($summary['total_outstanding']) }}
                </div>
                <div style="color: #718096; font-size: 10pt;">المتبقي</div>
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
                        <th><span class="ar">العميل</span></th>
                        <th class="text-center"><span class="ar">الفواتير</span></th>
                        <th class="text-left"><span class="ar">المبيعات</span></th>
                        <th class="text-left"><span class="ar">المحصل</span></th>
                        <th class="text-left"><span class="ar">المتبقي</span></th>
                        <th class="text-left"><span class="ar">متوسط</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td>
                                <strong>{{ $customer['customer_name'] }}</strong>
                                <br><small style="color: #718096;">{{ $customer['customer_code'] }}</small>
                            </td>
                            <td class="text-center">{{ $customer['invoices_count'] }}</td>
                            <td class="text-left money"><strong>{{ $currency($customer['total_sales']) }}</strong></td>
                            <td class="text-left money positive">{{ $currency($customer['total_paid']) }}</td>
                            <td class="text-left money {{ $customer['total_balance'] > 0 ? 'negative' : 'positive' }}">
                                {{ $currency($customer['total_balance']) }}
                            </td>
                            <td class="text-left money" style="color: #718096;">{{ $currency($customer['avg_invoice_value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>الإجمالي</strong></td>
                        <td class="text-center"><strong>{{ $summary['total_invoices'] }}</strong></td>
                        <td class="text-left money"><strong>{{ $currency($summary['total_sales']) }}</strong></td>
                        <td class="text-left money positive"><strong>{{ $currency($summary['total_collected']) }}</strong></td>
                        <td class="text-left money {{ $summary['total_outstanding'] > 0 ? 'negative' : '' }}">
                            <strong>{{ $currency($summary['total_outstanding']) }}</strong>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="no-data">لا توجد مبيعات خلال هذه الفترة</div>
        @endif
    </div>

@endsection