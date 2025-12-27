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
// Helper Functions
// =============================================================================

/**
 * Extract data from wrapped API response
 * Handles: { success: true, data: T } or direct T
 */
function unwrapResponse<T>(response: unknown): T | null {
    if (!response || typeof response !== 'object') return null;

    // If response has 'data' field (wrapped response)
    if ('data' in response) {
        return (response as { data: T }).data;
    }

    // Direct response
    return response as T;
}

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
            const response = await api.get<ApiResponse<{ report: DailyReport | null; working_date?: string } | DailyReport>>(
                endpoints.dailyReports.current
            );

            const data = unwrapResponse<{ report: DailyReport | null; working_date?: string } | DailyReport>(response);

            if (!data) return null;

            // Format 1: data is { report: {...}, working_date: ... }
            if ('report' in data) {
                return data.report;
            }

            // Format 2: data is DailyReport directly (has 'date' and 'status' fields)
            if ('date' in data && 'status' in data) {
                return data as DailyReport;
            }

            return null;
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
            const response = await api.get<ApiResponse<{ dates: AvailableDate[]; current_open: DailyReport | null } | AvailableDate[]>>(
                endpoints.dailyReports.availableDates
            );

            const data = unwrapResponse<{ dates: AvailableDate[]; current_open: DailyReport | null } | AvailableDate[]>(response);

            if (!data) return [];

            // Format 1: data is { dates: [...], current_open: ... }
            if ('dates' in data && Array.isArray(data.dates)) {
                return data.dates;
            }

            // Format 2: data is array directly
            if (Array.isArray(data)) {
                return data;
            }

            return [];
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

/**
 * Force close the current day (Admin only)
 * Used when normal close fails due to validation errors
 */
export function useForceCloseDay() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ reason }: { reason: string }) =>
            api.post<ApiResponse<DailyReport>>(endpoints.daily.forceClose, { reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['daily-report'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { DailyReport, AvailableDate };

