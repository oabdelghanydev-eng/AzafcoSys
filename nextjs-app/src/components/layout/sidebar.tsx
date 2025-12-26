'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { useUIStore } from '@/stores/ui-store';
import { useAuthStore } from '@/stores/auth-store';
import { PermissionGate } from '@/components/shared/permission-gate';
import {
    LayoutDashboard,
    FileText,
    Receipt,
    Truck,
    Users,
    Building2,
    Wallet,
    RotateCcw,
    PiggyBank,
    BarChart3,
    Settings,
    ChevronLeft,
    ChevronRight,
    Calendar,
    UserCog,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

interface NavItem {
    title: string;
    href: string;
    icon: React.ReactNode;
    permission?: string;
}

const navItems: NavItem[] = [
    {
        title: 'لوحة التحكم',
        href: '/',
        icon: <LayoutDashboard className="h-5 w-5" />,
    },
    {
        title: 'التقرير اليومي',
        href: '/daily',
        icon: <Calendar className="h-5 w-5" />,
        permission: 'daily_reports.view',
    },
    {
        title: 'الفواتير',
        href: '/invoices',
        icon: <FileText className="h-5 w-5" />,
        permission: 'invoices.view',
    },
    {
        title: 'التحصيلات',
        href: '/collections',
        icon: <Receipt className="h-5 w-5" />,
        permission: 'collections.view',
    },
    {
        title: 'الشحنات',
        href: '/shipments',
        icon: <Truck className="h-5 w-5" />,
        permission: 'shipments.view',
    },
    {
        title: 'العملاء',
        href: '/customers',
        icon: <Users className="h-5 w-5" />,
        permission: 'customers.view',
    },
    {
        title: 'الموردين',
        href: '/suppliers',
        icon: <Building2 className="h-5 w-5" />,
        permission: 'suppliers.view',
    },
    {
        title: 'المصروفات',
        href: '/expenses',
        icon: <Wallet className="h-5 w-5" />,
        permission: 'expenses.view',
    },
    {
        title: 'المرتجعات',
        href: '/returns',
        icon: <RotateCcw className="h-5 w-5" />,
        permission: 'returns.view',
    },
    {
        title: 'الحسابات',
        href: '/accounts',
        icon: <PiggyBank className="h-5 w-5" />,
        permission: 'accounts.view',
    },
    {
        title: 'التقارير',
        href: '/reports',
        icon: <BarChart3 className="h-5 w-5" />,
    },
    {
        title: 'الإعدادات',
        href: '/settings',
        icon: <Settings className="h-5 w-5" />,
        permission: 'settings.view',
    },
    {
        title: 'المستخدمين',
        href: '/users',
        icon: <UserCog className="h-5 w-5" />,
        permission: 'users.view',
    },
];

export function Sidebar() {
    const pathname = usePathname();
    const { sidebarCollapsed, setSidebarCollapsed } = useUIStore();
    const { user } = useAuthStore();

    return (
        <aside
            className={cn(
                'fixed left-0 top-0 z-40 h-screen bg-sidebar border-r border-sidebar-border transition-all duration-300',
                sidebarCollapsed ? 'w-16' : 'w-[280px]'
            )}
        >
            {/* Logo */}
            <div className="flex items-center justify-between h-16 px-4 border-b border-sidebar-border">
                {!sidebarCollapsed && (
                    <Link href="/" className="font-bold text-lg text-sidebar-foreground">
                        نظام المبيعات
                    </Link>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                    className="h-8 w-8"
                >
                    {sidebarCollapsed ? (
                        <ChevronRight className="h-4 w-4" />
                    ) : (
                        <ChevronLeft className="h-4 w-4" />
                    )}
                </Button>
            </div>

            {/* Navigation */}
            <nav className="flex-1 overflow-y-auto py-4">
                <ul className="space-y-1 px-2">
                    {navItems.map((item) => {
                        const isActive = pathname === item.href ||
                            (item.href !== '/' && pathname.startsWith(item.href));

                        const linkContent = (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors',
                                        'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                        isActive
                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                                            : 'text-sidebar-foreground',
                                        sidebarCollapsed && 'justify-center'
                                    )}
                                    title={sidebarCollapsed ? item.title : undefined}
                                >
                                    {item.icon}
                                    {!sidebarCollapsed && <span>{item.title}</span>}
                                </Link>
                            </li>
                        );

                        if (item.permission) {
                            return (
                                <PermissionGate key={item.href} permission={item.permission}>
                                    {linkContent}
                                </PermissionGate>
                            );
                        }

                        return linkContent;
                    })}
                </ul>
            </nav>

            {/* User Info */}
            {!sidebarCollapsed && user && (
                <div className="p-4 border-t border-sidebar-border">
                    <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-medium">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium truncate">{user.name}</p>
                            <p className="text-xs text-muted-foreground truncate">{user.email}</p>
                        </div>
                    </div>
                </div>
            )}
        </aside>
    );
}
