'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Collection,
    CollectionAllocation,
    CreateCollectionData,
    Invoice,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of collections with optional filters
 */
export function useCollections(params?: { search?: string; payment_method?: string }) {
    return useQuery({
        queryKey: ['collections', params?.search || '', params?.payment_method || ''],
        queryFn: () => {
            const queryParams = new URLSearchParams();
            if (params?.search) queryParams.set('search', params.search);
            if (params?.payment_method) queryParams.set('payment_method', params.payment_method);
            const url = queryParams.toString()
                ? `${endpoints.collections.list}?${queryParams}`
                : endpoints.collections.list;
            return api.get<ApiResponse<Collection[]>>(url);
        },
    });
}

/**
 * Fetch single collection by ID
 */
export function useCollection(id: number) {
    return useQuery({
        queryKey: ['collection', id],
        queryFn: () => api.get<ApiResponse<Collection>>(endpoints.collections.detail(id)),
        enabled: !!id,
    });
}

/**
 * Fetch unpaid invoices for a customer
 */
export function useUnpaidInvoices(customerId: number | undefined) {
    return useQuery({
        queryKey: ['unpaid-invoices', customerId],
        queryFn: () => api.get<ApiResponse<Invoice[]>>(`${endpoints.collections.unpaidInvoices}?customer_id=${customerId}`),
        enabled: !!customerId,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new collection
 */
export function useCreateCollection() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateCollectionData) =>
            api.post<ApiResponse<Collection>>(endpoints.collections.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['collections'] });
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] }); // Dashboard stats
            queryClient.invalidateQueries({ queryKey: ['daily'] }); // Daily report
        },
    });
}

/**
 * Cancel a collection
 */
export function useCancelCollection() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.collections.cancel(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['collections'] });
            queryClient.invalidateQueries({ queryKey: ['collection', id] });
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['accounts'] }); // Account balance
            queryClient.invalidateQueries({ queryKey: ['dashboard'] }); // Dashboard stats
            queryClient.invalidateQueries({ queryKey: ['daily'] }); // Daily report
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Collection, CollectionAllocation, CreateCollectionData };
