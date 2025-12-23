'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Return,
    ReturnItem,
    CreateReturnData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of returns
 */
export function useReturns() {
    return useQuery({
        queryKey: ['returns'],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Return[]> | Return[]>(endpoints.returns.list);
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && Array.isArray((response as ApiResponse<Return[]>).data)) {
                return (response as ApiResponse<Return[]>).data;
            }
            return (response as Return[]) || [];
        },
    });
}

/**
 * Fetch single return by ID
 */
export function useReturn(id: number) {
    return useQuery({
        queryKey: ['return', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Return> | Return>(endpoints.returns.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('return_number' in response)) {
                return (response as ApiResponse<Return>).data;
            }
            return response as Return;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new return
 */
export function useCreateReturn() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateReturnData) =>
            api.post<ApiResponse<Return>>(endpoints.returns.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['returns'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] }); // Balance changes
            queryClient.invalidateQueries({ queryKey: ['shipments'] }); // Stock may be restored
        },
    });
}

/**
 * Cancel a return
 */
export function useCancelReturn() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.returns.cancel(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['returns'] });
            queryClient.invalidateQueries({ queryKey: ['return', id] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Return, ReturnItem, CreateReturnData };
