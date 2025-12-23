import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin: boolean;
    permissions: string[];
}

interface AuthState {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;

    // Actions
    setUser: (user: User | null) => void;
    setToken: (token: string | null) => void;
    login: (user: User, token: string) => void;
    logout: () => void;
    setLoading: (loading: boolean) => void;

    // Permission helpers
    hasPermission: (permission: string) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
    hasAllPermissions: (permissions: string[]) => boolean;
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set, get) => ({
            user: null,
            token: null,
            isAuthenticated: false,
            isLoading: true,

            setUser: (user) => set({ user, isAuthenticated: !!user }),

            setToken: (token) => set({ token }),

            login: (user, token) => set({
                user,
                token,
                isAuthenticated: true,
                isLoading: false,
            }),

            logout: () => set({
                user: null,
                token: null,
                isAuthenticated: false,
            }),

            setLoading: (isLoading) => set({ isLoading }),

            hasPermission: (permission) => {
                const { user } = get();
                if (!user) return false;
                if (user.is_admin) return true;
                return user.permissions.includes(permission);
            },

            hasAnyPermission: (permissions) => {
                const { user } = get();
                if (!user) return false;
                if (user.is_admin) return true;
                return permissions.some(p => user.permissions.includes(p));
            },

            hasAllPermissions: (permissions) => {
                const { user } = get();
                if (!user) return false;
                if (user.is_admin) return true;
                return permissions.every(p => user.permissions.includes(p));
            },
        }),
        {
            name: 'auth-storage',
            partialize: (state) => ({
                user: state.user,
                token: state.token,
                isAuthenticated: state.isAuthenticated,
            }),
            onRehydrateStorage: () => (state) => {
                // Set loading to false after rehydration
                if (state) {
                    state.isLoading = false;
                }
            },
        }
    )
);
