'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    DashboardStats,
    DashboardActivity,
} from '@/types/api';

// =============================================================================
// Types
// =============================================================================

export interface FinancialSummary {
    company: {
        total_commission: number;
        total_expenses: number;
        net_profit: number;
    };
    suppliers: {
        total_sales: number;
        total_commission_deducted: number;
        total_expenses_on_behalf: number;
        total_payments_made: number;
        net_due_to_all: number;
    };
    supplier_breakdown: Array<{
        id: number;
        name: string;
        total_sales: number;
        commission: number;
        expenses: number;
        payments: number;
        net_due: number;
        stored_balance: number;
    }>;
    commission_rate: string;
}

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch dashboard statistics
 */
export function useDashboardStats() {
    return useQuery({
        queryKey: ['dashboard', 'stats'],
        queryFn: () => api.get<ApiResponse<DashboardStats>>(endpoints.dashboard.stats),
    });
}

/**
 * Fetch dashboard recent activity
 */
export function useDashboardActivity() {
    return useQuery({
        queryKey: ['dashboard', 'activity'],
        queryFn: () => api.get<ApiResponse<DashboardActivity>>(endpoints.dashboard.activity),
    });
}

/**
 * Fetch financial summary (company profit & supplier balances)
 */
export function useFinancialSummary() {
    return useQuery({
        queryKey: ['dashboard', 'financial-summary'],
        queryFn: () => api.get<ApiResponse<FinancialSummary>>(endpoints.dashboard.financialSummary),
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { DashboardStats, DashboardActivity };
