'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Invoice,
    InvoiceItem,
    CreateInvoiceData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of invoices with optional filters
 */
export function useInvoices(filters?: {
    customer_id?: number;
    date_from?: string;
    date_to?: string;
    status?: string;
    unpaid_only?: boolean;
}) {
    const params = new URLSearchParams();
    if (filters?.customer_id) params.append('customer_id', filters.customer_id.toString());
    if (filters?.date_from) params.append('date_from', filters.date_from);
    if (filters?.date_to) params.append('date_to', filters.date_to);
    if (filters?.status) params.append('status', filters.status);
    if (filters?.unpaid_only) params.append('unpaid_only', '1');

    const queryString = params.toString();
    const url = queryString ? `${endpoints.invoices.list}?${queryString}` : endpoints.invoices.list;

    return useQuery({
        queryKey: ['invoices', filters],
        queryFn: () => api.get<ApiResponse<Invoice[]>>(url),
    });
}

/**
 * Fetch single invoice by ID
 */
export function useInvoice(id: number) {
    return useQuery({
        queryKey: ['invoice', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Invoice> | Invoice>(endpoints.invoices.detail(id));
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && !('invoice_number' in response)) {
                return (response as ApiResponse<Invoice>).data;
            }
            return response as Invoice;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new invoice
 */
export function useCreateInvoice() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateInvoiceData) =>
            api.post<ApiResponse<Invoice>>(endpoints.invoices.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['shipments'] }); // Stock changes
        },
    });
}

/**
 * Cancel an invoice
 */
export function useCancelInvoice() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) =>
            api.post(endpoints.invoices.cancel(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['invoice', id] });
            queryClient.invalidateQueries({ queryKey: ['shipments'] }); // Stock restored
        },
    });
}

// =============================================================================
// Type Exports for convenience
// =============================================================================

export type { Invoice, InvoiceItem, CreateInvoiceData };
