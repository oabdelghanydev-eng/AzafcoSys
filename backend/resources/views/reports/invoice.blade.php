@extends('reports.layouts.pdf-layout')

@php
    use App\Helpers\ArabicPdfHelper;
    $L = fn($key) => ArabicPdfHelper::label($key);
    $currency = fn($amount) => ArabicPdfHelper::formatCurrency($amount);
    $number = fn($num) => number_format($num, $num == floor($num) ? 0 : 2);
@endphp

@section('title', 'فاتورة - ' . $invoice['invoice_number'])
@section('report-title')
    <span class="ar">فاتورة مبيعات</span>
    <span class="en" style="font-size: 0.8em; color: #cbd5e0;">Sales Invoice</span>
@endsection
@section('report-date')
    <span class="ar">{{ $invoice['invoice_number'] }}</span>
@endsection

@section('content')

    {{-- ═══════════════════════════════════════════════════════════════════
    1. INVOICE HEADER
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">1</span>
            <span class="ar">بيانات الفاتورة</span>
            <span class="en">Invoice Details</span>
        </div>

        <div class="info-box">
            <div class="info-row">
                <div class="info-label">رقم الفاتورة / Invoice Number</div>
                <div class="info-value">{{ $invoice['invoice_number'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">التاريخ / Date</div>
                <div class="info-value">{{ $invoice['date'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">العميل / Customer</div>
                <div class="info-value">{{ $customer['name'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">كود العميل / Customer Code</div>
                <div class="info-value">{{ $customer['code'] }}</div>
            </div>
            @if($customer['phone'] ?? null)
                <div class="info-row">
                    <div class="info-label">الهاتف / Phone</div>
                    <div class="info-value">{{ $customer['phone'] }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    2. INVOICE ITEMS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">2</span>
            <span class="ar">بنود الفاتورة</span>
            <span class="en">Invoice Items</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">
                        <span class="ar">المنتج</span>
                        <span class="en">Product</span>
                    </th>
                    <th style="width: 12%;">
                        <span class="ar">الكمية</span>
                        <span class="en">Qty</span>
                    </th>
                    <th style="width: 15%;">
                        <span class="ar">الوزن (ك)</span>
                        <span class="en">Weight (kg)</span>
                    </th>
                    <th style="width: 15%;">
                        <span class="ar">السعر/ك</span>
                        <span class="en">Price/kg</span>
                    </th>
                    <th style="width: 18%;">
                        <span class="ar">الإجمالي</span>
                        <span class="en">Total</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $number($item['quantity']) }}</td>
                        <td>{{ $number($item['total_weight']) }}</td>
                        <td class="money">{{ $currency($item['unit_price']) }}</td>
                        <td class="money">{{ $currency($item['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    3. TOTALS
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section">
        <div class="section-title">
            <span class="number">3</span>
            <span class="ar">الإجماليات</span>
            <span class="en">Totals</span>
        </div>

        <div class="summary-box" style="background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);">
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">المجموع الفرعي</span>
                    <span class="en">Subtotal</span>
                </span>
                <span class="summary-value money">{{ $currency($invoice['subtotal']) }}</span>
            </div>
            @if(($invoice['discount'] ?? 0) > 0)
                <div class="summary-row">
                    <span class="summary-label">
                        <span class="ar">(-) الخصم</span>
                        <span class="en">(-) Discount</span>
                    </span>
                    <span class="summary-value money positive">-{{ $currency($invoice['discount']) }}</span>
                </div>
            @endif
            <div class="summary-row total-row">
                <span class="summary-label">
                    <span class="ar">الإجمالي</span>
                    <span class="en">Total</span>
                </span>
                <span class="summary-value money"
                    style="font-size: 1.2em; color: #fbbf24;">{{ $currency($invoice['total']) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">
                    <span class="ar">المدفوع</span>
                    <span class="en">Paid</span>
                </span>
                <span class="summary-value money positive">{{ $currency($invoice['paid_amount'] ?? 0) }}</span>
            </div>
            <div class="summary-row total-row">
                <span class="summary-label">
                    <span class="ar">المتبقي</span>
                    <span class="en">Balance</span>
                </span>
                <span class="summary-value money {{ ($invoice['balance'] ?? 0) > 0 ? 'negative' : 'positive' }}">
                    {{ $currency($invoice['balance'] ?? $invoice['total']) }}
                </span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
    4. NOTES (if any)
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($invoice['notes'] ?? null)
        <div class="section">
            <div class="section-title">
                <span class="number">4</span>
                <span class="ar">ملاحظات</span>
                <span class="en">Notes</span>
            </div>
            <div class="info-box">
                <p style="margin: 0; color: #e2e8f0;">{{ $invoice['notes'] }}</p>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
    FOOTER SIGNATURE
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="section" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between;">
            <div style="width: 45%; text-align: center;">
                <div style="border-top: 1px solid #4a5568; padding-top: 5px; margin-top: 40px;">
                    <span class="ar">توقيع العميل</span>
                    <span class="en">Customer Signature</span>
                </div>
            </div>
            <div style="width: 45%; text-align: center;">
                <div style="border-top: 1px solid #4a5568; padding-top: 5px; margin-top: 40px;">
                    <span class="ar">توقيع المستلم</span>
                    <span class="en">Receiver Signature</span>
                </div>
            </div>
        </div>
    </div>

@endsection