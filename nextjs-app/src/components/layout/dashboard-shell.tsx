'use client';

import { ReactNode, useEffect } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { Sidebar } from '@/components/layout/sidebar';
import { Header } from '@/components/layout/header';
import { useUIStore } from '@/stores/ui-store';
import { useAuthStore } from '@/stores/auth-store';
import { cn } from '@/lib/utils';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { LoadingState } from '@/components/shared/loading-state';
import { VisuallyHidden } from '@radix-ui/react-visually-hidden';

function MobileSidebar() {
    const { mobileDrawerOpen, setMobileDrawerOpen } = useUIStore();

    return (
        <Sheet open={mobileDrawerOpen} onOpenChange={setMobileDrawerOpen}>
            <SheetContent side="left" className="w-[280px] p-0">
                <VisuallyHidden>
                    <SheetTitle>Navigation Menu</SheetTitle>
                </VisuallyHidden>
                <Sidebar />
            </SheetContent>
        </Sheet>
    );
}

interface DashboardShellProps {
    children: ReactNode;
}

export function DashboardShell({ children }: DashboardShellProps) {
    const { sidebarCollapsed } = useUIStore();
    const { isAuthenticated, isLoading: authLoading } = useAuthStore();
    const pathname = usePathname();
    const router = useRouter();

    // Simple client-side check - true after hydration
    const isClient = typeof window !== 'undefined';

    // Auth check for protected routes
    useEffect(() => {
        if (isClient && !authLoading) {
            const isAuthPage = pathname === '/login';

            if (!isAuthenticated && !isAuthPage) {
                router.push('/login');
            } else if (isAuthenticated && isAuthPage) {
                router.push('/');
            }
        }
    }, [isClient, authLoading, isAuthenticated, pathname, router]);

    // Don't render sidebar on login page
    const isAuthPage = pathname === '/login';

    if (isAuthPage) {
        return <>{children}</>;
    }

    // Show loading state during hydration or auth check
    if (!isClient || authLoading) {
        return (
            <div className="min-h-screen bg-background flex items-center justify-center">
                <LoadingState message="Loading..." />
            </div>
        );
    }

    // If not authenticated and not on login, don't render (will redirect)
    if (!isAuthenticated) {
        return (
            <div className="min-h-screen bg-background flex items-center justify-center">
                <LoadingState message="Redirecting to login..." />
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-background">
            {/* Desktop Sidebar */}
            <div className="hidden lg:block">
                <Sidebar />
            </div>

            {/* Mobile Sidebar */}
            <MobileSidebar />

            {/* Main Content */}
            <div
                className={cn(
                    'transition-all duration-300',
                    'lg:ml-[280px]',
                    sidebarCollapsed && 'lg:ml-16'
                )}
            >
                <Header />
                <main className="p-4 lg:p-6">{children}</main>
            </div>
        </div>
    );
}
