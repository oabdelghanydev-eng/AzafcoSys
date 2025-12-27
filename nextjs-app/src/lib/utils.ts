import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Format number as currency (QAR)
 * Standardized format: QAR 1,234.56
 */
export function formatCurrency(amount: number | null | undefined): string {
  if (amount === null || amount === undefined) return 'QAR 0.00';
  return `QAR ${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * Format number with commas
 * Example: 1234.567 -> 1,234.567
 */
export function formatNumber(num: number | null | undefined, decimals?: number): string {
  if (num === null || num === undefined) return '0';
  if (decimals !== undefined) {
    return num.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  }
  return num.toLocaleString('en-US');
}

/**
 * Format date consistently
 * Standardized format: Dec 23, 2025
 */
export function formatDate(date: string | Date | null | undefined): string {
  if (!date) return '-';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (isNaN(d.getTime())) return '-';
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Format weight with unit
 * Example: 1234.5 -> 1,234.5 kg
 */
export function formatWeight(weight: number | null | undefined): string {
  if (weight === null || weight === undefined) return '0 kg';
  return `${weight.toLocaleString('en-US', { maximumFractionDigits: 2 })} kg`;
}

