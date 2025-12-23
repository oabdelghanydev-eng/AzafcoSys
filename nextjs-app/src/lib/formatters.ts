import { config } from '@/lib/config';

/**
 * Format money with currency
 * Example: 1234.56 → "1,234.56 QAR"
 */
export function formatMoney(amount: number | string | null | undefined): string {
    if (amount === null || amount === undefined) return '0.00 ' + config.currency.symbol;

    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num) + ' ' + config.currency.symbol;
}

/**
 * Format quantity with 2 decimal places
 * Example: 125.5 → "125.50"
 */
export function formatQuantity(quantity: number | string | null | undefined): string {
    if (quantity === null || quantity === undefined) return '0.00';

    const num = typeof quantity === 'string' ? parseFloat(quantity) : quantity;

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Format date in readable format
 * Example: "2025-12-22" → "December 22, 2025"
 */
export function formatDate(date: string | Date | null | undefined): string {
    if (!date) return '-';

    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(d);
}

/**
 * Format date in short format
 * Example: "2025-12-22" → "Dec 22, 2025"
 */
export function formatDateShort(date: string | Date | null | undefined): string {
    if (!date) return '-';

    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(d);
}

/**
 * Format date for API
 * Example: Date → "2025-12-22"
 */
export function formatDateForApi(date: Date): string {
    return date.toISOString().split('T')[0];
}

/**
 * Format integer (no decimals)
 * Example: 1234 → "1,234"
 */
export function formatInteger(num: number | string | null | undefined): string {
    if (num === null || num === undefined) return '0';

    const n = typeof num === 'string' ? parseInt(num, 10) : num;

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 0,
    }).format(n);
}
