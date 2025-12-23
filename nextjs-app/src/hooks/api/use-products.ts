'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Product,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of products
 */
export function useProducts() {
    return useQuery({
        queryKey: ['products'],
        queryFn: () => api.get<ApiResponse<Product[]>>(endpoints.products.list),
    });
}

/**
 * Fetch single product by ID
 */
export function useProduct(id: number) {
    return useQuery({
        queryKey: ['product', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Product> | Product>(endpoints.products.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('name' in response)) {
                return (response as ApiResponse<Product>).data;
            }
            return response as Product;
        },
        enabled: !!id,
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Product };
