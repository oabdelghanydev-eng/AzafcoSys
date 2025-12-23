'use client';

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    DashboardStats,
    DashboardActivity,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch dashboard statistics
 */
export function useDashboardStats() {
    return useQuery({
        queryKey: ['dashboard', 'stats'],
        queryFn: () => api.get<ApiResponse<DashboardStats>>(endpoints.dashboard.stats),
    });
}

/**
 * Fetch dashboard recent activity
 */
export function useDashboardActivity() {
    return useQuery({
        queryKey: ['dashboard', 'activity'],
        queryFn: () => api.get<ApiResponse<DashboardActivity>>(endpoints.dashboard.activity),
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { DashboardStats, DashboardActivity };
