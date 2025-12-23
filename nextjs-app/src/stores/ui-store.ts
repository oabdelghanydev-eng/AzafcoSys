import { create } from 'zustand';

interface UIState {
    // Sidebar
    sidebarOpen: boolean;
    sidebarCollapsed: boolean;

    // Mobile drawer
    mobileDrawerOpen: boolean;

    // Working date
    workingDate: string | null;

    // Language
    locale: 'en' | 'ar';
    direction: 'ltr' | 'rtl';

    // Actions
    toggleSidebar: () => void;
    setSidebarOpen: (open: boolean) => void;
    setSidebarCollapsed: (collapsed: boolean) => void;

    toggleMobileDrawer: () => void;
    setMobileDrawerOpen: (open: boolean) => void;

    setWorkingDate: (date: string | null) => void;

    setLocale: (locale: 'en' | 'ar') => void;
}

export const useUIStore = create<UIState>((set) => ({
    // Initial state
    sidebarOpen: true,
    sidebarCollapsed: false,
    mobileDrawerOpen: false,
    workingDate: null,
    locale: 'en',
    direction: 'ltr',

    // Sidebar actions
    toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
    setSidebarOpen: (open) => set({ sidebarOpen: open }),
    setSidebarCollapsed: (collapsed) => set({ sidebarCollapsed: collapsed }),

    // Mobile drawer actions
    toggleMobileDrawer: () => set((state) => ({
        mobileDrawerOpen: !state.mobileDrawerOpen
    })),
    setMobileDrawerOpen: (open) => set({ mobileDrawerOpen: open }),

    // Working date
    setWorkingDate: (date) => set({ workingDate: date }),

    // Locale
    setLocale: (locale) => set({
        locale,
        direction: locale === 'ar' ? 'rtl' : 'ltr'
    }),
}));
