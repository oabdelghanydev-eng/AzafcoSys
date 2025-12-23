'use client';

import { ReactNode } from 'react';
import { useUIStore } from '@/stores/ui-store';
import { cn } from '@/lib/utils';
import { Sidebar } from '@/components/layout/sidebar';
import { Header } from '@/components/layout/header';
import { Sheet, SheetContent } from '@/components/ui/sheet';

// Mobile Sidebar Content (reusing sidebar nav items)
function MobileSidebar() {
    const { mobileDrawerOpen, setMobileDrawerOpen } = useUIStore();

    return (
        <Sheet open={mobileDrawerOpen} onOpenChange={setMobileDrawerOpen}>
            <SheetContent side="left" className="w-[280px] p-0">
                <div className="h-full">
                    {/* Can reuse sidebar content here or create separate mobile nav */}
                    <div className="p-4 border-b">
                        <h2 className="font-bold text-lg">Sales System</h2>
                    </div>
                    <nav className="p-4">
                        <p className="text-sm text-muted-foreground">
                            Mobile navigation - same as sidebar
                        </p>
                    </nav>
                </div>
            </SheetContent>
        </Sheet>
    );
}

interface DashboardLayoutProps {
    children: ReactNode;
}

export default function DashboardLayout({ children }: DashboardLayoutProps) {
    const { sidebarCollapsed } = useUIStore();

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
