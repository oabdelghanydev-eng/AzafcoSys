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
 * Fetch list of collections
 */
export function useCollections() {
    return useQuery({
        queryKey: ['collections'],
        queryFn: () => api.get<ApiResponse<Collection[]>>(endpoints.collections.list),
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
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Collection, CollectionAllocation, CreateCollectionData };
