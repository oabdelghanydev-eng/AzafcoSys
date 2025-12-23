'use client';

import Link from 'next/link';
import {
    DollarSign,
    Receipt,
    FileText,
    Wallet,
    Plus,
    Calendar,
    Loader2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/shared/stat-card';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatInteger, formatDateShort as _formatDateShort } from '@/lib/formatters';
import { useDashboardStats, useDashboardActivity } from '@/hooks/api/use-dashboard';

export default function DashboardPage() {
    const { data: statsData, isLoading: statsLoading, error: statsError, refetch: refetchStats } = useDashboardStats();
    const { data: activityData, isLoading: activityLoading } = useDashboardActivity();

    // Use API data or fallback to defaults
    const stats = statsData?.data || {
        today_sales: 0,
        today_collections: 0,
        invoices_count: 0,
        cashbox_balance: 0,
    };

    const activity = activityData?.data || { invoices: [], collections: [], expenses: [] };

    // Combine and sort recent activity
    const recentActivity = [
        ...activity.invoices.slice(0, 3).map((inv: { id: number; customer?: { name: string }; total: number; created_at: string }) => ({
            type: 'invoice' as const,
            id: inv.id,
            label: inv.customer?.name || 'Customer',
            amount: inv.total,
            time: inv.created_at
        })),
        ...activity.collections.slice(0, 3).map((col: { id: number; customer?: { name: string }; amount: number; created_at?: string }) => ({
            type: 'collection' as const,
            id: col.id,
            label: col.customer?.name || 'Customer',
            amount: col.amount,
            time: col.created_at
        })),
        ...activity.expenses.slice(0, 2).map((exp: { id: number; description: string; amount: number; created_at?: string }) => ({
            type: 'expense' as const,
            id: exp.id,
            label: exp.description || 'Expense',
            amount: exp.amount,
            time: exp.created_at
        })),
    ].slice(0, 5);

    if (statsLoading) {
        return <LoadingState message="Loading dashboard..." />;
    }

    if (statsError) {
        return (
            <ErrorState
                title="Failed to load dashboard"
                message="Could not connect to the server"
                retry={() => refetchStats()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Page Title */}
            <div>
                <h1 className="text-2xl font-bold">Dashboard</h1>
                <p className="text-muted-foreground">
                    Welcome back! Here&apos;s your business overview.
                </p>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    title="Today's Sales"
                    value={formatMoney(stats.today_sales)}
                    icon={<DollarSign className="h-5 w-5" />}
                />
                <StatCard
                    title="Today's Collections"
                    value={formatMoney(stats.today_collections)}
                    icon={<Receipt className="h-5 w-5" />}
                />
                <StatCard
                    title="Invoices Today"
                    value={formatInteger(stats.invoices_count)}
                    icon={<FileText className="h-5 w-5" />}
                />
                <StatCard
                    title="Cashbox Balance"
                    value={formatMoney(stats.cashbox_balance)}
                    icon={<Wallet className="h-5 w-5" />}
                />
            </div>

            {/* Quick Actions */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Quick Actions</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <PermissionGate permission="invoices.create">
                            <Button asChild variant="outline" className="h-auto py-4 flex-col gap-2">
                                <Link href="/invoices/new">
                                    <Plus className="h-5 w-5" />
                                    <span>New Invoice</span>
                                </Link>
                            </Button>
                        </PermissionGate>

                        <PermissionGate permission="collections.create">
                            <Button asChild variant="outline" className="h-auto py-4 flex-col gap-2">
                                <Link href="/collections/new">
                                    <Receipt className="h-5 w-5" />
                                    <span>New Collection</span>
                                </Link>
                            </Button>
                        </PermissionGate>

                        <PermissionGate permission="expenses.create">
                            <Button asChild variant="outline" className="h-auto py-4 flex-col gap-2">
                                <Link href="/expenses/new">
                                    <Wallet className="h-5 w-5" />
                                    <span>New Expense</span>
                                </Link>
                            </Button>
                        </PermissionGate>

                        <PermissionGate permission="daily_reports.create">
                            <Button asChild variant="outline" className="h-auto py-4 flex-col gap-2">
                                <Link href="/daily">
                                    <Calendar className="h-5 w-5" />
                                    <span>Open Day</span>
                                </Link>
                            </Button>
                        </PermissionGate>
                    </div>
                </CardContent>
            </Card>

            {/* Recent Activity */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Recent Activity</CardTitle>
                </CardHeader>
                <CardContent>
                    {activityLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        </div>
                    ) : recentActivity.length === 0 ? (
                        <p className="text-center text-muted-foreground py-8">No recent activity</p>
                    ) : (
                        <div className="space-y-4">
                            {recentActivity.map((activity, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between py-2 border-b last:border-0"
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={`h-8 w-8 rounded-full flex items-center justify-center ${activity.type === 'invoice'
                                                ? 'bg-blue-100 text-blue-600'
                                                : activity.type === 'collection'
                                                    ? 'bg-green-100 text-green-600'
                                                    : 'bg-orange-100 text-orange-600'
                                                }`}
                                        >
                                            {activity.type === 'invoice' ? (
                                                <FileText className="h-4 w-4" />
                                            ) : activity.type === 'collection' ? (
                                                <Receipt className="h-4 w-4" />
                                            ) : (
                                                <Wallet className="h-4 w-4" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {activity.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {activity.type.charAt(0).toUpperCase() + activity.type.slice(1)} #{activity.id}
                                            </p>
                                        </div>
                                    </div>
                                    <p className="text-sm font-medium money">
                                        {formatMoney(activity.amount)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
