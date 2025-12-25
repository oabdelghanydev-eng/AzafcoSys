'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type { ApiResponse } from '@/types/api';

// =============================================================================
// Types
// =============================================================================

export interface ProfitLossReport {
    period: { from: string | null; to: string | null };
    revenue: {
        commission: { total_sales: number; commission_rate: number; amount: number };
        total: number;
    };
    expenses: {
        by_category: Array<{ category: string; amount: number; count: number }>;
        by_payment_method: { cash: number; bank: number };
        total: number;
    };
    summary: {
        total_revenue: number;
        total_expenses: number;
        net_profit: number;
        profit_margin: number;
    };
}

export interface CashFlowReport {
    period: { from: string | null; to: string | null };
    inflows: {
        by_payment_method: { cash: number; bank: number };
        total: number;
    };
    outflows: {
        by_type: { company_expenses: number; supplier_expenses: number; supplier_payments: number };
        total: number;
    };
    account_balances: { cashbox: number; bank: number };
    summary: {
        total_inflows: number;
        total_outflows: number;
        net_flow: number;
        total_liquidity: number;
    };
}

export interface CustomerAgingReport {
    as_of_date: string;
    customers: Array<{
        customer_id: number;
        customer_code: string;
        customer_name: string;
        total_balance: number;
        invoices_count: number;
        aging: { current: number; days_31_60: number; days_61_90: number; over_90: number };
        oldest_invoice_days: number;
    }>;
    totals: { current: number; days_31_60: number; days_61_90: number; over_90: number; total: number };
    summary: { total_customers: number; total_debt: number; current_percentage: number; overdue_percentage: number };
}

export interface SalesByProductReport {
    period: { from: string | null; to: string | null };
    products: Array<{
        product_id: number;
        product_name: string;
        quantity: number;
        weight: number;
        revenue: number;
        invoices_count: number;
    }>;
    summary: { total_products: number; total_quantity: number; total_weight: number; total_revenue: number; avg_price_per_kg: number };
}

export interface InventoryStockReport {
    as_of_date: string;
    products: Array<{
        product_id: number;
        product_name: string;
        total_cartons: number;
        total_weight: number;
        shipments_count: number;
    }>;
    summary: { total_products: number; total_cartons: number; total_weight: number; shipments_count: number };
}

// =============================================================================
// Query Hooks
// =============================================================================

export function useProfitLossReport(dateFrom?: string, dateTo?: string) {
    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    return useQuery({
        queryKey: ['reports', 'profit-loss', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<ProfitLossReport>>(
            `${endpoints.reports.profitLoss}${queryString ? `?${queryString}` : ''}`
        ),
    });
}

export function useCashFlowReport(dateFrom?: string, dateTo?: string) {
    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    return useQuery({
        queryKey: ['reports', 'cash-flow', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<CashFlowReport>>(
            `${endpoints.reports.cashFlow}${queryString ? `?${queryString}` : ''}`
        ),
    });
}

export function useCustomerAgingReport() {
    return useQuery({
        queryKey: ['reports', 'customer-aging'],
        queryFn: () => api.get<ApiResponse<CustomerAgingReport>>(endpoints.reports.customerAging),
    });
}

export function useSalesByProductReport(dateFrom?: string, dateTo?: string) {
    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    return useQuery({
        queryKey: ['reports', 'sales-by-product', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<SalesByProductReport>>(
            `${endpoints.reports.salesByProduct}${queryString ? `?${queryString}` : ''}`
        ),
    });
}

export function useInventoryStockReport() {
    return useQuery({
        queryKey: ['reports', 'inventory-stock'],
        queryFn: () => api.get<ApiResponse<InventoryStockReport>>(endpoints.reports.inventoryStock),
    });
}
