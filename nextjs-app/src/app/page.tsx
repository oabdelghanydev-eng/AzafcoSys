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
    TrendingUp,
    Building2,
    Truck,
    ArrowDownRight,
    ArrowUpRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/shared/stat-card';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatInteger, formatDateShort as _formatDateShort } from '@/lib/formatters';
import { useDashboardStats, useDashboardActivity, useFinancialSummary } from '@/hooks/api/use-dashboard';

export default function DashboardPage() {
    const { data: statsData, isLoading: statsLoading, error: statsError, refetch: refetchStats } = useDashboardStats();
    const { data: activityData, isLoading: activityLoading } = useDashboardActivity();
    const { data: financialData, isLoading: financialLoading } = useFinancialSummary();

    // Use API data or fallback to defaults
    const stats = statsData?.data || {
        today_sales: 0,
        today_collections: 0,
        invoices_count: 0,
        cashbox_balance: 0,
    };

    const activity = activityData?.data || { invoices: [], collections: [], expenses: [] };
    const financial = financialData?.data;

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

            {/* Financial Summary Section */}
            {financialLoading ? (
                <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                </div>
            ) : financial && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Company Profit Card */}
                    <Card className="border-l-4 border-l-emerald-500 bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/20 dark:to-background">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <div className="bg-emerald-100 dark:bg-emerald-900 p-2 rounded-lg">
                                    <Building2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                Company Profit
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-baseline justify-between">
                                <span className="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatMoney(financial.company.net_profit)}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    Net Profit
                                </span>
                            </div>
                            <div className="space-y-2 pt-2 border-t">
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1 text-muted-foreground">
                                        <ArrowUpRight className="h-3 w-3 text-emerald-500" />
                                        Commission ({financial.commission_rate})
                                    </span>
                                    <span className="font-medium text-emerald-600">
                                        +{formatMoney(financial.company.total_commission)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1 text-muted-foreground">
                                        <ArrowDownRight className="h-3 w-3 text-red-500" />
                                        Company Expenses
                                    </span>
                                    <span className="font-medium text-red-600">
                                        -{formatMoney(financial.company.total_expenses)}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Supplier Dues Card */}
                    <Card className="border-l-4 border-l-blue-500 bg-gradient-to-br from-blue-50 to-white dark:from-blue-950/20 dark:to-background">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <div className="bg-blue-100 dark:bg-blue-900 p-2 rounded-lg">
                                    <Truck className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                Due to Suppliers
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-baseline justify-between">
                                <span className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                    {formatMoney(financial.suppliers.net_due_to_all)}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    Total Due
                                </span>
                            </div>
                            <div className="space-y-2 pt-2 border-t">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Total Sales</span>
                                    <span className="font-medium">{formatMoney(financial.suppliers.total_sales)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Commission Deducted</span>
                                    <span className="font-medium text-red-600">-{formatMoney(financial.suppliers.total_commission_deducted)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Expenses on Behalf</span>
                                    <span className="font-medium text-red-600">-{formatMoney(financial.suppliers.total_expenses_on_behalf)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Payments Made</span>
                                    <span className="font-medium text-red-600">-{formatMoney(financial.suppliers.total_payments_made)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Supplier Breakdown */}
            {financial && financial.supplier_breakdown.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <TrendingUp className="h-5 w-5" />
                            Supplier Balances
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left py-2 font-medium">Supplier</th>
                                        <th className="text-right py-2 font-medium">Sales</th>
                                        <th className="text-right py-2 font-medium">Commission</th>
                                        <th className="text-right py-2 font-medium">Expenses</th>
                                        <th className="text-right py-2 font-medium">Payments</th>
                                        <th className="text-right py-2 font-medium text-blue-600">Net Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {financial.supplier_breakdown.map((supplier) => (
                                        <tr key={supplier.id} className="border-b last:border-0 hover:bg-muted/50">
                                            <td className="py-3 font-medium">{supplier.name}</td>
                                            <td className="py-3 text-right">{formatMoney(supplier.total_sales)}</td>
                                            <td className="py-3 text-right text-red-600">-{formatMoney(supplier.commission)}</td>
                                            <td className="py-3 text-right text-red-600">-{formatMoney(supplier.expenses)}</td>
                                            <td className="py-3 text-right text-red-600">-{formatMoney(supplier.payments)}</td>
                                            <td className="py-3 text-right font-bold text-blue-600">{formatMoney(supplier.net_due)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

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
