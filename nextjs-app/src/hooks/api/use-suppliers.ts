'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Supplier,
    CreateSupplierData,
    UpdateSupplierData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of suppliers
 */
export function useSuppliers() {
    return useQuery({
        queryKey: ['suppliers'],
        queryFn: () => api.get<ApiResponse<Supplier[]>>(endpoints.suppliers.list),
    });
}

/**
 * Fetch single supplier by ID
 */
export function useSupplier(id: number) {
    return useQuery({
        queryKey: ['supplier', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Supplier> | Supplier>(endpoints.suppliers.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('supplier_code' in response)) {
                return (response as ApiResponse<Supplier>).data;
            }
            return response as Supplier;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new supplier
 */
export function useCreateSupplier() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateSupplierData) =>
            api.post<ApiResponse<Supplier>>(endpoints.suppliers.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['suppliers'] });
        },
    });
}

/**
 * Update an existing supplier
 */
export function useUpdateSupplier() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: UpdateSupplierData }) =>
            api.put<ApiResponse<Supplier>>(endpoints.suppliers.update(id), data),
        onSuccess: (_, { id }) => {
            queryClient.invalidateQueries({ queryKey: ['suppliers'] });
            queryClient.invalidateQueries({ queryKey: ['supplier', id] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Supplier, CreateSupplierData, UpdateSupplierData };
