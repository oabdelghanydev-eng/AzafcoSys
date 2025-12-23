'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type {
    ApiResponse,
    Settings,
    UpdateSettingsData,
} from '@/types/api';

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch application settings
 */
export function useSettings() {
    return useQuery({
        queryKey: ['settings'],
        queryFn: async () => {
            const response = await api.get<ApiResponse<Settings> | Settings>(endpoints.settings.get);
            // Handle both wrapped and unwrapped responses
            if (response && typeof response === 'object' && 'data' in response && !('company_name' in response)) {
                return (response as ApiResponse<Settings>).data;
            }
            return response as Settings;
        },
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Update application settings
 */
export function useUpdateSettings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateSettingsData) =>
            api.put<ApiResponse<Settings>>(endpoints.settings.update, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settings'] });
        },
    });
}

// =============================================================================
// Type Exports
// =============================================================================

export type { Settings, UpdateSettingsData };
