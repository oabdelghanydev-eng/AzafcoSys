'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    AccountsSummary,
    Transaction,
    TransferData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch accounts summary (cashbox, bank, total)
 */
export function useAccountsSummary() {
    return useQuery({
        queryKey: ['accounts', 'summary'],
        queryFn: () => api.get<ApiResponse<AccountsSummary>>(endpoints.accounts.summary),
    });
}

/**
 * Fetch cashbox transactions
 */
export function useCashboxTransactions() {
    return useQuery({
        queryKey: ['cashbox', 'transactions'],
        queryFn: () => api.get<ApiResponse<Transaction[]>>(endpoints.accounts.cashboxTransactions),
    });
}

/**
 * Fetch bank transactions
 */
export function useBankTransactions() {
    return useQuery({
        queryKey: ['bank', 'transactions'],
        queryFn: () => api.get<ApiResponse<Transaction[]>>(endpoints.accounts.bankTransactions),
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

interface DepositWithdrawParams {
    account: 'cashbox' | 'bank';
    amount: number;
    notes?: string;
}

/**
 * Deposit funds to an account
 */
export function useDeposit() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ account, amount, notes }: DepositWithdrawParams) =>
            api.post(account === 'cashbox' ? endpoints.accounts.cashboxDeposit : endpoints.accounts.bankDeposit, { amount, notes }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['cashbox'] });
            queryClient.invalidateQueries({ queryKey: ['bank'] });
        },
    });
}

/**
 * Withdraw funds from an account
 */
export function useWithdraw() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ account, amount, notes }: DepositWithdrawParams) =>
            api.post(account === 'cashbox' ? endpoints.accounts.cashboxWithdraw : endpoints.accounts.bankWithdraw, { amount, notes }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['cashbox'] });
            queryClient.invalidateQueries({ queryKey: ['bank'] });
        },
    });
}

/**
 * Transfer funds between accounts
 */
export function useTransfer() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: TransferData) =>
            api.post(endpoints.accounts.transfer, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['cashbox'] });
            queryClient.invalidateQueries({ queryKey: ['bank'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { AccountsSummary, Transaction, TransferData };
