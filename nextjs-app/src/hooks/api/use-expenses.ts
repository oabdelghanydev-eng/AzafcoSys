'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Expense,
    CreateExpenseData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of expenses
 */
export function useExpenses() {
    return useQuery({
        queryKey: ['expenses'],
        queryFn: () => api.get<ApiResponse<Expense[]>>(endpoints.expenses.list),
    });
}

/**
 * Fetch single expense by ID
 */
export function useExpense(id: number) {
    return useQuery({
        queryKey: ['expense', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Expense> | Expense>(endpoints.expenses.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('description' in response)) {
                return (response as ApiResponse<Expense>).data;
            }
            return response as Expense;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new expense
 */
export function useCreateExpense() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateExpenseData) =>
            api.post<ApiResponse<Expense>>(endpoints.expenses.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['expenses'] });
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['suppliers'] }); // Balance changes for supplier expenses
            queryClient.invalidateQueries({ queryKey: ['dashboard'] }); // Dashboard stats
            queryClient.invalidateQueries({ queryKey: ['daily'] }); // Daily report
        },
    });
}

/**
 * Cancel an expense
 */
export function useCancelExpense() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.expenses.cancel(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['expenses'] });
            queryClient.invalidateQueries({ queryKey: ['expense', id] });
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['suppliers'] }); // Balance changes
            queryClient.invalidateQueries({ queryKey: ['dashboard'] }); // Dashboard stats
            queryClient.invalidateQueries({ queryKey: ['daily'] }); // Daily report
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Expense, CreateExpenseData };
