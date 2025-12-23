'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Customer,
    CreateCustomerData,
    UpdateCustomerData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of customers
 */
export function useCustomers() {
    return useQuery({
        queryKey: ['customers'],
        queryFn: () => api.get<ApiResponse<Customer[]>>(endpoints.customers.list),
    });
}

/**
 * Fetch single customer by ID
 */
export function useCustomer(id: number) {
    return useQuery({
        queryKey: ['customer', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Customer> | Customer>(endpoints.customers.detail(id));
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && !('customer_code' in response)) {
                return (response as ApiResponse<Customer>).data;
            }
            return response as Customer;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new customer
 */
export function useCreateCustomer() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateCustomerData) =>
            api.post<ApiResponse<Customer>>(endpoints.customers.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['customers'] });
        },
    });
}

/**
 * Update an existing customer
 */
export function useUpdateCustomer() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: UpdateCustomerData }) =>
            api.put<ApiResponse<Customer>>(endpoints.customers.update(id), data),
        onSuccess: (_, { id }) => {
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['customer', id] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Customer, CreateCustomerData, UpdateCustomerData };
