import { ApiResponseError } from './api';

/**
 * تحسين 2025-12-13: Utility functions for handling API errors in components
 */

/**
 * Extract error message from unknown error
 * Supports ApiResponseError, Error, and string
 */
export function getErrorMessage(
    error: unknown,
    locale: 'ar' | 'en' = 'ar'
): string {
    if (error instanceof ApiResponseError) {
        return error.getMessage(locale);
    }

    if (error instanceof Error) {
        return error.message;
    }

    if (typeof error === 'string') {
        return error;
    }

    return locale === 'ar' ? 'حدث خطأ غير متوقع' : 'An unexpected error occurred';
}

/**
 * Extract error code from unknown error
 */
export function getErrorCode(error: unknown): string | undefined {
    if (error instanceof ApiResponseError) {
        return error.code;
    }

    // Legacy support for error.code property
    if (error && typeof error === 'object' && 'code' in error) {
        return (error as { code?: string }).code;
    }

    return undefined;
}

/**
 * Check if error is a specific business error
 */
export function isErrorCode(error: unknown, code: string): boolean {
    return getErrorCode(error) === code;
}

/**
 * Common error codes for frontend use
 */
export const ErrorCodes = {
    // Inventory
    INV_001: 'INV_001', // Insufficient stock

    // Invoice
    INVOICE_001: 'INVOICE_001', // Cannot delete invoice
    INVOICE_002: 'INVOICE_002', // Cannot reactivate cancelled
    INVOICE_003: 'INVOICE_003', // Discount exceeds subtotal

    // Collection
    COL_001: 'COL_001', // Cannot delete collection
    COL_002: 'COL_002', // Cannot reactivate cancelled

    // Shipment
    SHP_001: 'SHP_001', // Cannot modify settled shipment
    SHP_002: 'SHP_002', // Cannot delete with sales
} as const;

export type ErrorCode = typeof ErrorCodes[keyof typeof ErrorCodes];
