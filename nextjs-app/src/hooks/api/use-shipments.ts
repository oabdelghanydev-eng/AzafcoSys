'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Shipment,
    ShipmentItem,
    StockItem,
    CreateShipmentData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of shipments
 */
export function useShipments() {
    return useQuery({
        queryKey: ['shipments'],
        queryFn: () => api.get<ApiResponse<Shipment[]>>(endpoints.shipments.list),
    });
}

/**
 * Fetch single shipment by ID
 */
export function useShipment(id: number) {
    return useQuery({
        queryKey: ['shipment', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Shipment> | Shipment>(endpoints.shipments.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('status' in response)) {
                return (response as ApiResponse<Shipment>).data;
            }
            return response as Shipment;
        },
        enabled: !!id,
    });
}

/**
 * Fetch current stock (grouped by product)
 */
export function useStock() {
    return useQuery({
        queryKey: ['stock'],
        queryFn: () => api.get<ApiResponse<StockItem[]>>(endpoints.shipments.stock),
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new shipment
 */
export function useCreateShipment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateShipmentData) =>
            api.post<ApiResponse<Shipment>>(endpoints.shipments.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['shipments'] });
            queryClient.invalidateQueries({ queryKey: ['stock'] });
        },
    });
}

/**
 * Close a shipment
 */
export function useCloseShipment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.shipments.close(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['shipments'] });
            queryClient.invalidateQueries({ queryKey: ['shipment', id] });
        },
    });
}

/**
 * Settle a shipment
 */
export function useSettleShipment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.shipments.settle(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['shipments'] });
            queryClient.invalidateQueries({ queryKey: ['shipment', id] });
            queryClient.invalidateQueries({ queryKey: ['suppliers'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Shipment, ShipmentItem, StockItem, CreateShipmentData };
