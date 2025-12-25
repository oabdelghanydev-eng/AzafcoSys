@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
@endphp

@section('title', 'كشف حساب عميل - ' . $customer['name'])
@section('report-title')
    <span class="ar">كشف حساب عميل</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Customer Statement</span>
@endsection
@section('report-date')
    <span class="ar">{{ $customer['name'] }}</span>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. CUSTOMER INFORMATION
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">بيانات العميل</span>
            <span class="en">Customer Information</span>
        </div>

        <div class="info-box">
            <div class="info-row">
                <div class="info-label">الكود / Code</div>
                <div class="info-value">{{ $customer['code'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">الاسم / Name</div>
                <div class="info-value">{{ $customer['name'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">الرصيد الحالي / Current Balance</div>
                <div class="info-value money">{{ $currency($customer['current_balance']) }}</div>
            </div>
            @if($period['from'] || $period['to'])
                <div class="info-row">
                    <div class="info-label">الفترة / Period</div>
                    <div class="info-value">
                        {{ $period['from'] ?? 'البداية' }} - {{ $period['to'] ?? 'اليوم' }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. SUMMARY
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">ملخص الحركة</span>
            <span class="en">Movement Summary</span>
        </div>

        <div class="summary-box" style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);">
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">إجمالي الفواتير</span>
                    <span class="en">Total Invoices</span>
                </span>
                <span class="summary-value money">{{ $currency($summary['total_invoices']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">(-) إجمالي التحصيلات</span>
                    <span class="en">(-) Total Collections</span>
                </span>
                <span class="summary-value money positive">-{{ $currency($summary['total_collections']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">(-) إجمالي المرتجعات</span>
                    <span class="en">(-) Total Returns</span>
                </span>
                <span class="summary-value money positive">-{{ $currency($summary['total_returns'] ?? 0) }}</span>
            </div>
            @if(($summary['total_credit_notes'] ?? 0) > 0)
                <div class="summary-row">
                    <span class="summary-label">
                        <span class="ar">(-) إشعارات دائنة</span>
                        <span class="en">(-) Credit Notes</span>
                    </span>
                    <span class="summary-value money positive">-{{ $currency($summary['total_credit_notes']) }}</span>
                </div>
            @endif
            @if(($summary['total_debit_notes'] ?? 0) > 0)
                <div class="summary-row">
                    <span class="summary-label">
                        <span class="ar">(+) إشعارات مدينة</span>
                        <span class="en">(+) Debit Notes</span>
                    </span>
                    <span class="summary-value money">+{{ $currency($summary['total_debit_notes']) }}</span>
                </div>
            @endif
            <div class="final-total">
                <span class="summary-label">
                    <strong>
                        <span class="ar">الرصيد</span>
                        <span class="en">Balance</span>
                    </strong>
                </span>
                <span class="summary-value money">
                    <strong>{{ $currency($customer['current_balance']) }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. TRANSACTIONS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">3</span>
            <span class="ar">كشف الحركات</span>
            <span class="en">Transactions</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">
                        <span class="ar">التاريخ</span>
                        <span class="en">Date</span>
                    </th>
                    <th>
                        <span class="ar">المرجع</span>
                        <span class="en">Reference</span>
                    </th>
                    <th>
                        <span class="ar">البيان</span>
                        <span class="en">Description</span>
                    </th>
                    <th class="text-left" style="width: 90px;">
                        <span class="ar">مدين</span>
                        <span class="en">Debit</span>
                    </th>
                    <th class="text-left" style="width: 90px;">
                        <span class="ar">دائن</span>
                        <span class="en">Credit</span>
                    </th>
                    <th class="text-left" style="width: 90px;">
                        <span class="ar">الرصيد</span>
                        <span class="en">Balance</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d/m/Y') }}</td>
                        <td>{{ $tx['reference'] }}</td>
                        <td>
                            @if($tx['type'] === 'invoice')
                                <span class="badge badge-info">فاتورة</span>
                            @elseif($tx['type'] === 'collection')
                                <span class="badge badge-success">تحصيل</span>
                            @elseif($tx['type'] === 'return')
                                <span class="badge badge-warning">مرتجع</span>
                            @elseif($tx['type'] === 'credit_note')
                                <span class="badge badge-success">إشعار دائن</span>
                            @elseif($tx['type'] === 'debit_note')
                                <span class="badge badge-danger">إشعار مدين</span>
                            @endif
                            {{ $tx['description'] }}
                        </td>
                        <td class="text-left money">
                            @if($tx['debit'] > 0)
                                {{ $currency($tx['debit']) }}
                            @endif
                        </td>
                        <td class="text-left money positive">
                            @if($tx['credit'] > 0)
                                {{ $currency($tx['credit']) }}
                            @endif
                        </td>
                        <td class="text-left money">
                            <strong>{{ $currency($tx['balance']) }}</strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    4. STATISTICS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="info-box" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <div style="font-size: 24px; font-weight: bold; color: #667eea;">{{ $summary['invoices_count'] }}</div>
                <div style="color: #a0aec0; font-size: 11px;">
                    <span class="ar">عدد الفواتير</span>
                </div>
            </div>
            <div>
                <div style="font-size: 24px; font-weight: bold; color: #48bb78;">{{ $summary['collections_count'] }}</div>
                <div style="color: #a0aec0; font-size: 11px;">
                    <span class="ar">عدد التحصيلات</span>
                </div>
            </div>
            <div>
                <div style="font-size: 24px; font-weight: bold; color: #ed8936;">
                    {{ $currency($customer['current_balance']) }}
                </div>
                <div style="color: #a0aec0; font-size: 11px;">
                    <span class="ar">الرصيد الحالي</span>
                </div>
            </div>
        </div>
    </div>

@endsection