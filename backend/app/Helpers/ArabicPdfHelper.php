<?php

namespace App\Helpers;

/**
 * Arabic PDF Helper
 *
 * Comprehensive helper for bilingual Arabic/English PDF generation
 * Provides labels, currency formatting, and RTL support
 */
class ArabicPdfHelper
{
    /**
     * Check if text contains Arabic characters
     */
    public static function isArabic(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }

    /**
     * Format currency with symbol from settings
     */
    public static function formatCurrency(float $amount, bool $showSymbol = true): string
    {
        $formatted = number_format($amount, 2);

        if ($showSymbol) {
            $currencySymbol = \App\Models\Setting::getValue('currency_symbol', 'ر.ق');
            return $formatted . ' ' . $currencySymbol;
        }

        return $formatted;
    }

    /**
     * Format number with Arabic numerals option
     */
    public static function formatNumber(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals);
    }

    /**
     * Get all bilingual labels for PDF reports
     */
    public static function getLabels(): array
    {
        return [
            // Report Titles
            'daily_closing_report' => ['ar' => 'تقرير الإغلاق اليومي', 'en' => 'Daily Closing Report'],
            'shipment_settlement_report' => ['ar' => 'تقرير تسوية الشحنة', 'en' => 'Shipment Settlement Report'],
            'customer_statement' => ['ar' => 'كشف حساب العميل', 'en' => 'Customer Statement'],

            // Section Headers - Daily Report
            'sales_invoices' => ['ar' => 'فواتير المبيعات', 'en' => 'Sales Invoices'],
            'collections' => ['ar' => 'التحصيلات', 'en' => 'Collections'],
            'expenses' => ['ar' => 'المصروفات', 'en' => 'Expenses'],
            'transfers' => ['ar' => 'التحويلات', 'en' => 'Transfers'],
            'new_shipments' => ['ar' => 'شحنات جديدة', 'en' => 'New Shipments'],
            'daily_summary' => ['ar' => 'ملخص اليوم', 'en' => 'Daily Summary'],
            'balances' => ['ar' => 'الأرصدة', 'en' => 'Balances'],
            'remaining_inventory' => ['ar' => 'المخزون المتبقي', 'en' => 'Remaining Inventory'],

            // Section Headers - Settlement Report
            'shipment_info' => ['ar' => 'بيانات الشحنة', 'en' => 'Shipment Information'],
            'sales_by_product' => ['ar' => 'المبيعات حسب المنتج', 'en' => 'Sales by Product'],
            'returns_previous' => ['ar' => 'مرتجعات الشحنة السابقة', 'en' => 'Returns from Previous Shipment'],
            'inventory_movement' => ['ar' => 'حركة المخزون', 'en' => 'Inventory Movement'],
            'weight_analysis' => ['ar' => 'تحليل الوزن', 'en' => 'Weight Analysis'],
            'supplier_expenses' => ['ar' => 'مصروفات المورد', 'en' => 'Supplier Expenses'],
            'financial_summary' => ['ar' => 'الملخص المالي للمورد', 'en' => 'Supplier Financial Summary'],

            // Table Headers
            'invoice_number' => ['ar' => 'رقم الفاتورة', 'en' => 'Invoice #'],
            'receipt_number' => ['ar' => 'رقم الإيصال', 'en' => 'Receipt #'],
            'customer' => ['ar' => 'العميل', 'en' => 'Customer'],
            'product' => ['ar' => 'المنتج', 'en' => 'Product'],
            'quantity' => ['ar' => 'الكمية', 'en' => 'Qty'],
            'unit_weight' => ['ar' => 'وزن الوحدة', 'en' => 'Unit Wt.'],
            'total_weight' => ['ar' => 'الوزن الكلي', 'en' => 'Total Wt.'],
            'weight' => ['ar' => 'الوزن', 'en' => 'Weight'],
            'amount' => ['ar' => 'المبلغ', 'en' => 'Amount'],
            'method' => ['ar' => 'طريقة الدفع', 'en' => 'Method'],
            'description' => ['ar' => 'الوصف', 'en' => 'Description'],
            'type' => ['ar' => 'النوع', 'en' => 'Type'],
            'from' => ['ar' => 'من', 'en' => 'From'],
            'to' => ['ar' => 'إلى', 'en' => 'To'],
            'supplier' => ['ar' => 'المورد', 'en' => 'Supplier'],
            'items' => ['ar' => 'الأصناف', 'en' => 'Items'],
            'date' => ['ar' => 'التاريخ', 'en' => 'Date'],
            'total' => ['ar' => 'الإجمالي', 'en' => 'Total'],
            'avg_price' => ['ar' => 'متوسط السعر', 'en' => 'Avg Price'],

            // Summary Labels
            'total_sales' => ['ar' => 'إجمالي المبيعات', 'en' => 'Total Sales'],
            'total_collections' => ['ar' => 'إجمالي التحصيلات', 'en' => 'Total Collections'],
            'total_expenses' => ['ar' => 'إجمالي المصروفات', 'en' => 'Total Expenses'],
            'cash' => ['ar' => 'نقدي', 'en' => 'Cash'],
            'bank' => ['ar' => 'بنك', 'en' => 'Bank'],
            'company' => ['ar' => 'شركة', 'en' => 'Company'],
            'market_balance' => ['ar' => 'رصيد السوق (ديون العملاء)', 'en' => 'Market Balance (Customer Debts)'],
            'cashbox_balance' => ['ar' => 'رصيد الخزينة', 'en' => 'Cashbox Balance'],
            'bank_balance' => ['ar' => 'رصيد البنك', 'en' => 'Bank Balance'],

            // Settlement Specific
            'shipment_number' => ['ar' => 'رقم الشحنة', 'en' => 'Shipment Number'],
            'arrival_date' => ['ar' => 'تاريخ الوصول', 'en' => 'Arrival Date'],
            'settlement_date' => ['ar' => 'تاريخ التسوية', 'en' => 'Settlement Date'],
            'duration' => ['ar' => 'المدة', 'en' => 'Duration'],
            'days' => ['ar' => 'يوم', 'en' => 'days'],
            'qty_sold' => ['ar' => 'الكمية المباعة', 'en' => 'Qty Sold'],
            'weight_sold' => ['ar' => 'الوزن المباع', 'en' => 'Weight Sold'],
            'incoming' => ['ar' => 'الوارد', 'en' => 'Incoming'],
            'carryover_next' => ['ar' => 'محوّل للشحنة التالية', 'en' => 'Carried Over to Next Shipment'],
            'total_weight_in' => ['ar' => 'إجمالي الوزن الوارد', 'en' => 'Total Weight In'],
            'total_weight_out' => ['ar' => 'إجمالي الوزن الصادر', 'en' => 'Total Weight Out'],
            'difference' => ['ar' => 'الفرق', 'en' => 'Difference'],
            'total_returns' => ['ar' => 'إجمالي المرتجعات', 'en' => 'Total Returns'],
            'total_supplier_expenses' => ['ar' => 'إجمالي مصروفات المورد', 'en' => 'Total Supplier Expenses'],
            'net_sales' => ['ar' => 'صافي المبيعات', 'en' => 'Net Sales'],
            'company_commission' => ['ar' => 'عمولة الشركة', 'en' => 'Company Commission'],
            'previous_balance' => ['ar' => 'الرصيد السابق', 'en' => 'Previous Balance'],
            'payments_to_supplier' => ['ar' => 'مدفوعات للمورد', 'en' => 'Payments to Supplier'],
            'final_balance' => ['ar' => 'الرصيد النهائي للمورد', 'en' => 'FINAL SUPPLIER BALANCE'],
            'returns_deduction' => ['ar' => 'خصم مرتجعات الشحنة السابقة', 'en' => 'Returns from Previous Shipment'],

            // Status & Notes
            'no_data' => ['ar' => 'لا توجد بيانات', 'en' => 'No data available'],
            'no_sales' => ['ar' => 'لا توجد فواتير مبيعات لهذا اليوم', 'en' => 'No sales invoices for this day'],
            'no_collections' => ['ar' => 'لا توجد تحصيلات لهذا اليوم', 'en' => 'No collections for this day'],
            'no_expenses' => ['ar' => 'لا توجد مصروفات لهذا اليوم', 'en' => 'No expenses for this day'],
            'generated' => ['ar' => 'تم الإنشاء', 'en' => 'Generated'],
            'page' => ['ar' => 'صفحة', 'en' => 'Page'],
        ];
    }

    /**
     * Get a specific label
     */
    public static function label(string $key, string $lang = 'both'): string
    {
        $labels = self::getLabels();

        if (!isset($labels[$key])) {
            return $key;
        }

        $label = $labels[$key];

        return match ($lang) {
            'ar' => $label['ar'],
            'en' => $label['en'],
            'both' => $label['ar'] . ' / ' . $label['en'],
            default => $label['ar']
        };
    }

    /**
     * Wrap Arabic text in RTL span
     */
    public static function wrapRtl(string $text): string
    {
        if (!self::isArabic($text)) {
            return $text;
        }

        return '<span style="direction: rtl; unicode-bidi: bidi-override;">' . $text . '</span>';
    }

    /**
     * Get payment method label
     */
    public static function paymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي / Cash',
            'bank' => 'بنك / Bank',
            'check' => 'شيك / Check',
            default => $method
        };
    }

    /**
     * Get expense type label
     */
    public static function expenseType(string $type): string
    {
        return match ($type) {
            'company' => 'شركة / Company',
            'supplier' => 'مورد / Supplier',
            default => $type
        };
    }
}
