'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { useAuthStore, User } from '@/stores/auth-store';

interface LoginData {
    email: string;
    password: string;
}

interface LoginResponse {
    token: string;
    user: User;
}

export function useLogin() {
    const router = useRouter();
    const { login } = useAuthStore();

    return useMutation({
        mutationFn: (data: LoginData) =>
            api.post<LoginResponse>(endpoints.auth.login, data),
        onSuccess: (response) => {
            login(response.user, response.token);
            router.push('/');
        },
    });
}

export function useLogout() {
    const router = useRouter();
    const { logout } = useAuthStore();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => api.post(endpoints.auth.logout),
        onSuccess: () => {
            logout();
            queryClient.clear();
            router.push('/login');
        },
        onError: () => {
            // Logout locally even if API fails
            logout();
            queryClient.clear();
            router.push('/login');
        },
    });
}

export function useCurrentUser() {
    const { setUser, setLoading } = useAuthStore();

    return useMutation({
        mutationFn: () => api.get<{ data: User }>(endpoints.auth.me),
        onSuccess: (response) => {
            setUser(response.data);
            setLoading(false);
        },
        onError: () => {
            setLoading(false);
        },
    });
}
