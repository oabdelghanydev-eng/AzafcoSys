'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type { ApiResponse } from '@/types/api';

// =============================================================================
// Types
// =============================================================================

export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_locked: boolean;
    locked_at?: string;
    permissions: string[];
    created_at: string;
}

export interface PermissionGroup {
    label: string;
    permissions: string[];
}

export interface PermissionsResponse {
    permissions: string[];
    grouped: Record<string, PermissionGroup>;
}

export interface CreateUserData {
    name: string;
    email: string;
    password?: string;
    permissions?: string[];
    is_admin?: boolean;
}

export interface UpdateUserData {
    name?: string;
    email?: string;
    is_admin?: boolean;
}

// =============================================================================
// Query Hooks
// =============================================================================

/**
 * Fetch list of users
 */
export function useUsers(filters?: { search?: string; locked?: boolean }) {
    const params = new URLSearchParams();
    if (filters?.search) params.append('search', filters.search);
    if (filters?.locked !== undefined) params.append('locked', filters.locked ? '1' : '0');

    const queryString = params.toString();
    const url = queryString ? `${endpoints.users.list}?${queryString}` : endpoints.users.list;

    return useQuery({
        queryKey: ['users', filters],
        queryFn: async () => {
            const response = await api.get<ApiResponse<User[]> | { data: User[] }>(url);
            // Handle paginated response
            if (response && typeof response === 'object' && 'data' in response) {
                const data = (response as { data: User[] | { data: User[] } }).data;
                if (Array.isArray(data)) {
                    return data;
                }
                if (data && typeof data === 'object' && 'data' in data) {
                    return (data as { data: User[] }).data;
                }
            }
            return [];
        },
    });
}

/**
 * Fetch single user by ID
 */
export function useUser(id: number) {
    return useQuery({
        queryKey: ['user', id],
        queryFn: async () => {
            const response = await api.get<ApiResponse<User> | User>(endpoints.users.detail(id));
            if (response && typeof response === 'object' && 'data' in response && !('email' in response)) {
                return (response as ApiResponse<User>).data;
            }
            return response as User;
        },
        enabled: !!id,
    });
}

/**
 * Fetch all available permissions
 */
export function usePermissions() {
    return useQuery({
        queryKey: ['permissions'],
        queryFn: async () => {
            const response = await api.get<ApiResponse<PermissionsResponse>>(endpoints.permissions.list);
            if (response && typeof response === 'object' && 'data' in response) {
                return (response as ApiResponse<PermissionsResponse>).data;
            }
            return response as PermissionsResponse;
        },
    });
}

// =============================================================================
// Mutation Hooks
// =============================================================================

/**
 * Create a new user
 */
export function useCreateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateUserData) =>
            api.post<ApiResponse<User>>(endpoints.users.create, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
        },
    });
}

/**
 * Update user
 */
export function useUpdateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: UpdateUserData }) =>
            api.put<ApiResponse<User>>(endpoints.users.update(id), data),
        onSuccess: (_, { id }) => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            queryClient.invalidateQueries({ queryKey: ['user', id] });
        },
    });
}

/**
 * Delete user
 */
export function useDeleteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete(endpoints.users.delete(id)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
        },
    });
}

/**
 * Update user permissions
 */
export function useUpdatePermissions() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, permissions }: { id: number; permissions: string[] }) =>
            api.put<ApiResponse<User>>(endpoints.users.permissions(id), { permissions }),
        onSuccess: (_, { id }) => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            queryClient.invalidateQueries({ queryKey: ['user', id] });
        },
    });
}

/**
 * Lock user account
 */
export function useLockUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.users.lock(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            queryClient.invalidateQueries({ queryKey: ['user', id] });
        },
    });
}

/**
 * Unlock user account
 */
export function useUnlockUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.post(endpoints.users.unlock(id)),
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            queryClient.invalidateQueries({ queryKey: ['user', id] });
        },
    });
}
