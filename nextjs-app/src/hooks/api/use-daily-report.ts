'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    DailyReport,
    AvailableDate,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch current day (open daily report)
 */
export function useCurrentDay() {
    return useQuery({
        queryKey: ['daily-report', 'current'],
        queryFn: async () => {
            const response = await api.get<ApiResponse<DailyReport> | DailyReport>(endpoints.dailyReports.current);
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && !('status' in response)) {
                return (response as ApiResponse<DailyReport>).data;
            }
            return response as DailyReport;
        },
    });
}

/**
 * Fetch available dates for opening
 */
export function useAvailableDates() {
    return useQuery({
        queryKey: ['daily-report', 'available-dates'],
        queryFn: async () => {
            const response = await api.get<ApiResponse<AvailableDate[]> | AvailableDate[]>(endpoints.dailyReports.availableDates);
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && Array.isArray((response as ApiResponse<AvailableDate[]>).data)) {
                return (response as ApiResponse<AvailableDate[]>).data;
            }
            return (response as AvailableDate[]) || [];
        },
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Open a new day
 */
export function useOpenDay() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (date: string) =>
            api.post<ApiResponse<DailyReport>>(endpoints.dailyReports.open, { date }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['daily-report'] });
        },
    });
}

/**
 * Close the current day
 */
export function useCloseDay() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () =>
            api.post<ApiResponse<DailyReport>>(endpoints.dailyReports.close),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['daily-report'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { DailyReport, AvailableDate };
