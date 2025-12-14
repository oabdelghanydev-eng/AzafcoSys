/**
 * Format number as Egyptian currency
 */
export function formatCurrency(amount: number): string {
    return (Math.round(amount * 100) / 100).toLocaleString('ar-EG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }) + ' ج.م';
}

/**
 * Safe calculation to avoid floating point issues
 */
export function safeSum(...values: number[]): number {
    return Math.round(values.reduce((a, b) => a + b, 0) * 100) / 100;
}

/**
 * Safe multiply for currency
 */
export function safeMultiply(a: number, b: number): number {
    return Math.round(a * b * 100) / 100;
}

/**
 * Format date for Arabic display
 */
export function formatDate(date: string | Date): string {
    return new Date(date).toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Get today's date in YYYY-MM-DD format
 */
export function getTodayISO(): string {
    return new Date().toISOString().split('T')[0];
}
