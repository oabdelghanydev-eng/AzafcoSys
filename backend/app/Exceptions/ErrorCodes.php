<?php

namespace App\Exceptions;

/**
 * Error Codes Reference
 * تحسين 2025-12-13: توحيد أكواد الأخطاء
 */
class ErrorCodes
{
    // === Inventory Errors (INV_xxx) ===
    public const INV_001 = 'INV_001'; // Insufficient stock
    public const INV_002 = 'INV_002'; // Product not found
    public const INV_003 = 'INV_003'; // Invalid quantity

    // === Invoice Errors (INV_xxx) ===
    public const INVOICE_001 = 'INVOICE_001'; // Cannot delete invoice
    public const INVOICE_002 = 'INVOICE_002'; // Cannot reactivate cancelled
    public const INVOICE_003 = 'INVOICE_003'; // Discount exceeds subtotal
    public const INVOICE_004 = 'INVOICE_004'; // Edit window expired
    public const INVOICE_005 = 'INVOICE_005'; // Total must be positive

    // === Collection Errors (COL_xxx) ===
    public const COL_001 = 'COL_001'; // Cannot delete collection
    public const COL_002 = 'COL_002'; // Cannot reactivate cancelled
    public const COL_003 = 'COL_003'; // Amount exceeds invoice balance
    public const COL_004 = 'COL_004'; // Allocation exceeds collection

    // === Shipment Errors (SHP_xxx) ===
    public const SHP_001 = 'SHP_001'; // Cannot modify settled shipment
    public const SHP_002 = 'SHP_002'; // Cannot delete with sales
    public const SHP_003 = 'SHP_003'; // Cannot unsettle with sold items
    public const SHP_004 = 'SHP_004'; // Invalid status transition

    // === Customer Errors (CUST_xxx) ===
    public const CUST_001 = 'CUST_001'; // Customer not found
    public const CUST_002 = 'CUST_002'; // Customer has balance

    // === Validation Errors (VAL_xxx) ===
    public const VAL_001 = 'VAL_001'; // Required field missing
    public const VAL_002 = 'VAL_002'; // Invalid format
    public const VAL_003 = 'VAL_003'; // Value out of range

    // === Correction Errors (COR_xxx) ===
    public const COR_001 = 'COR_001'; // Not pending approval
    public const COR_002 = 'COR_002'; // Cannot approve own correction
    public const COR_003 = 'COR_003'; // Invalid correction type

    // === Adjustment Errors (ADJ_xxx) ===
    public const ADJ_001 = 'ADJ_001'; // Cannot adjust settled shipment
    public const ADJ_002 = 'ADJ_002'; // Quantity cannot be negative
    public const ADJ_003 = 'ADJ_003'; // Cannot reduce below sold
    public const ADJ_004 = 'ADJ_004'; // Not pending approval
    public const ADJ_005 = 'ADJ_005'; // Cannot approve own adjustment

    // === User Errors (USR_xxx) ===
    public const USR_001 = 'USR_001'; // Email already exists
    public const USR_002 = 'USR_002'; // Cannot delete yourself
    public const USR_003 = 'USR_003'; // Cannot delete last admin
    public const USR_004 = 'USR_004'; // Account is locked
    public const USR_005 = 'USR_005'; // Cannot modify own permissions
    public const USR_006 = 'USR_006'; // User not found

    // === Auth Errors (AUTH_xxx) ===
    public const AUTH_001 = 'AUTH_001'; // Unauthenticated
    public const AUTH_002 = 'AUTH_002'; // Unauthorized
    public const AUTH_003 = 'AUTH_003'; // Invalid credentials

    // === Financial Errors (FIN_xxx) ===
    public const FIN_001 = 'FIN_001'; // Cashbox not found
    public const FIN_002 = 'FIN_002'; // Insufficient balance
    public const FIN_003 = 'FIN_003'; // Bank account not found

    // === Treasury Errors (TRS_xxx) ===
    public const TRS_001 = 'TRS_001'; // Insufficient cashbox balance
    public const TRS_002 = 'TRS_002'; // Transfer failed

    /**
     * Get Arabic message for error code
     */
    public static function getMessage(string $code): string
    {
        return match ($code) {
                // Inventory
            self::INV_001 => 'المخزون غير كافي',
            self::INV_002 => 'الصنف غير موجود',
            self::INV_003 => 'الكمية غير صالحة',

                // Invoice
            self::INVOICE_001 => 'لا يمكن حذف الفواتير. استخدم الإلغاء',
            self::INVOICE_002 => 'لا يمكن إعادة تفعيل فاتورة ملغاة',
            self::INVOICE_003 => 'الخصم أكبر من إجمالي الأصناف',
            self::INVOICE_004 => 'انتهت مهلة تعديل الفاتورة',
            self::INVOICE_005 => 'إجمالي الفاتورة يجب أن يكون أكبر من صفر',

                // Collection
            self::COL_001 => 'لا يمكن حذف التحصيلات. استخدم الإلغاء',
            self::COL_002 => 'لا يمكن إعادة تفعيل تحصيل ملغى',
            self::COL_003 => 'المبلغ أكبر من رصيد الفاتورة',
            self::COL_004 => 'إجمالي التوزيع أكبر من مبلغ التحصيل',

                // Shipment
            self::SHP_001 => 'لا يمكن تعديل شحنة مُصفاة',
            self::SHP_002 => 'لا يمكن حذف شحنة لها مبيعات',
            self::SHP_003 => 'لا يمكن إلغاء تصفية شحنة لها مبيعات',
            self::SHP_004 => 'تغيير الحالة غير مسموح',

                // Customer
            self::CUST_001 => 'العميل غير موجود',
            self::CUST_002 => 'العميل لديه رصيد معلق',

                // Validation
            self::VAL_001 => 'هذا الحقل مطلوب',
            self::VAL_002 => 'صيغة البيانات غير صحيحة',
            self::VAL_003 => 'القيمة خارج النطاق المسموح',

                // Correction
            self::COR_001 => 'التصحيح ليس في انتظار الموافقة',
            self::COR_002 => 'لا يمكنك الموافقة على تصحيحك الخاص',
            self::COR_003 => 'نوع التصحيح غير صالح',

                // Adjustment
            self::ADJ_001 => 'لا يمكن تعديل مخزون شحنة مُصفاة',
            self::ADJ_002 => 'الكمية لا يمكن أن تكون سالبة',
            self::ADJ_003 => 'لا يمكن تقليل الكمية لأقل من المباع',
            self::ADJ_004 => 'التسوية ليست في انتظار الموافقة',
            self::ADJ_005 => 'لا يمكنك الموافقة على تسويتك الخاصة',

                // User
            self::USR_001 => 'البريد الإلكتروني مسجل مسبقاً',
            self::USR_002 => 'لا يمكنك حذف نفسك',
            self::USR_003 => 'لا يمكن حذف آخر مسؤول',
            self::USR_004 => 'الحساب مقفل',
            self::USR_005 => 'لا يمكنك تعديل صلاحياتك الخاصة',
            self::USR_006 => 'المستخدم غير موجود',

                // Auth
            self::AUTH_001 => 'غير مصرح. يرجى تسجيل الدخول أولاً',
            self::AUTH_002 => 'ليس لديك صلاحية للقيام بهذا الإجراء',
            self::AUTH_003 => 'بيانات الدخول غير صحيحة',

                // Financial
            self::FIN_001 => 'الخزنة غير موجودة',
            self::FIN_002 => 'الرصيد غير كافي',
            self::FIN_003 => 'الحساب البنكي غير موجود',

                // Treasury
            self::TRS_001 => 'رصيد الخزنة غير كافي',
            self::TRS_002 => 'فشل التحويل',

            default => 'حدث خطأ غير متوقع',
        };
    }

    /**
     * Get English message for error code
     */
    public static function getMessageEn(string $code): string
    {
        return match ($code) {
                // Inventory
            self::INV_001 => 'Insufficient stock',
            self::INV_002 => 'Product not found',
            self::INV_003 => 'Invalid quantity',

                // Invoice
            self::INVOICE_001 => 'Cannot delete invoices. Use cancellation',
            self::INVOICE_002 => 'Cannot reactivate cancelled invoice',
            self::INVOICE_003 => 'Discount exceeds subtotal',
            self::INVOICE_004 => 'Invoice edit window expired',
            self::INVOICE_005 => 'Invoice total must be positive',

                // Collection
            self::COL_001 => 'Cannot delete collections. Use cancellation',
            self::COL_002 => 'Cannot reactivate cancelled collection',
            self::COL_003 => 'Amount exceeds invoice balance',
            self::COL_004 => 'Total allocation exceeds collection amount',

                // Shipment
            self::SHP_001 => 'Cannot modify settled shipment',
            self::SHP_002 => 'Cannot delete shipment with sales',
            self::SHP_003 => 'Cannot unsettle shipment with sold items',
            self::SHP_004 => 'Invalid status transition',

                // Customer
            self::CUST_001 => 'Customer not found',
            self::CUST_002 => 'Customer has pending balance',

                // Validation
            self::VAL_001 => 'This field is required',
            self::VAL_002 => 'Invalid data format',
            self::VAL_003 => 'Value out of allowed range',

                // Correction
            self::COR_001 => 'Correction is not pending approval',
            self::COR_002 => 'Cannot approve your own correction (Maker-Checker)',
            self::COR_003 => 'Invalid correction type',

                // Adjustment
            self::ADJ_001 => 'Cannot adjust inventory of settled shipment',
            self::ADJ_002 => 'Quantity cannot be negative',
            self::ADJ_003 => 'Cannot reduce quantity below sold amount',
            self::ADJ_004 => 'Adjustment is not pending approval',
            self::ADJ_005 => 'Cannot approve your own adjustment (Maker-Checker)',

                // User
            self::USR_001 => 'Email already exists',
            self::USR_002 => 'Cannot delete yourself',
            self::USR_003 => 'Cannot delete last admin',
            self::USR_004 => 'Account is locked',
            self::USR_005 => 'Cannot modify your own permissions',
            self::USR_006 => 'User not found',

                // Auth
            self::AUTH_001 => 'Unauthenticated. Please login first',
            self::AUTH_002 => 'Unauthorized to perform this action',
            self::AUTH_003 => 'Invalid credentials',

                // Financial
            self::FIN_001 => 'Cashbox not found',
            self::FIN_002 => 'Insufficient balance',
            self::FIN_003 => 'Bank account not found',

                // Treasury
            self::TRS_001 => 'Insufficient cashbox balance',
            self::TRS_002 => 'Transfer failed',

            default => 'An unexpected error occurred',
        };
    }
}
