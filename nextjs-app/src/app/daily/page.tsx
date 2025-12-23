'use client';

import { useState } from 'react';
import { Calendar, Download, Play, Square, AlertCircle, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatCard } from '@/components/shared/stat-card';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useUIStore } from '@/stores/ui-store';
import { useCurrentDay, useAvailableDates, useOpenDay, useCloseDay } from '@/hooks/api/use-daily-report';
import { config } from '@/lib/config';

export default function DailyReportPage() {
    const [selectedDate, setSelectedDate] = useState<string>('');
    const [showCloseConfirm, setShowCloseConfirm] = useState(false);
    const { setWorkingDate } = useUIStore();

    // API hooks - these now return unwrapped data
    const { data: currentDay, isLoading: currentLoading, error: currentError, refetch } = useCurrentDay();
    const { data: availableDates, isLoading: datesLoading } = useAvailableDates();
    const openDay = useOpenDay();
    const closeDay = useCloseDay();

    // Check if day is open - API returns status: 'open' | 'closed'
    const isDayOpen = currentDay?.status === 'open';

    const handleOpenDay = async () => {
        if (!selectedDate) {
            toast.error('Please select a date');
            return;
        }

        try {
            await openDay.mutateAsync(selectedDate);
            setWorkingDate(selectedDate);
            toast.success(`Day ${selectedDate} opened successfully`);
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to open day');
        }
    };

    const handleCloseDay = async () => {
        try {
            await closeDay.mutateAsync();
            setWorkingDate(null);
            toast.success('Day closed successfully');
            setShowCloseConfirm(false);
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to close day');
        }
    };

    const handleDownloadPDF = () => {
        if (!currentDay?.date) return;
        window.open(`${config.apiUrl}/reports/daily/${currentDay.date}/pdf`, '_blank');
    };

    if (currentLoading || datesLoading) {
        return <LoadingState message="Loading..." />;
    }

    if (currentError) {
        return (
            <ErrorState
                title="Failed to load daily report"
                message="Could not fetch daily report data"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Page Header */}
            <div>
                <h1 className="text-2xl font-bold">Daily Report</h1>
                <p className="text-muted-foreground">
                    Manage daily operations and generate reports
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Open Day Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Open New Day
                        </CardTitle>
                        <CardDescription>
                            Select a date to start recording transactions
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {Array.isArray(availableDates) && availableDates.length > 0 ? (
                            <Select value={selectedDate} onValueChange={setSelectedDate}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select available date" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableDates.map((item: { date: string; day_name?: string }) => {
                                        const dateStr = typeof item === 'string' ? item : item.date;
                                        const dayName = typeof item === 'object' ? item.day_name : '';
                                        return (
                                            <SelectItem key={dateStr} value={dateStr}>
                                                {formatDateShort(dateStr)} {dayName && `(${dayName})`}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        ) : (
                            <p className="text-sm text-muted-foreground">No available dates</p>
                        )}

                        <PermissionGate permission="daily_reports.create">
                            <Button
                                onClick={handleOpenDay}
                                disabled={!selectedDate || openDay.isPending || isDayOpen}
                                className="w-full touch-target"
                            >
                                {openDay.isPending ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Play className="mr-2 h-4 w-4" />
                                )}
                                Open Day
                            </Button>
                        </PermissionGate>

                        {isDayOpen && (
                            <div className="flex items-center gap-2 text-sm text-orange-600">
                                <AlertCircle className="h-4 w-4" />
                                A day is already open. Close it first.
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Current Day Card */}
                {isDayOpen && currentDay && (
                    <Card className="border-primary">
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between">
                                <span className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                                    Current Working Day
                                </span>
                                <span className="text-lg">{formatDateShort(currentDay.date)}</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p className="text-2xl font-bold">{currentDay.invoices_count || 0}</p>
                                    <p className="text-xs text-muted-foreground">Invoices</p>
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{currentDay.collections_count || 0}</p>
                                    <p className="text-xs text-muted-foreground">Collections</p>
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{currentDay.expenses_count || 0}</p>
                                    <p className="text-xs text-muted-foreground">Expenses</p>
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <PermissionGate permission="daily_reports.update">
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowCloseConfirm(true)}
                                        disabled={closeDay.isPending}
                                        className="flex-1 touch-target"
                                    >
                                        <Square className="mr-2 h-4 w-4" />
                                        Close Day
                                    </Button>
                                </PermissionGate>

                                <Button
                                    variant="outline"
                                    onClick={handleDownloadPDF}
                                    className="touch-target"
                                >
                                    <Download className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* No Day Open */}
                {!isDayOpen && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-8 text-center">
                            <Calendar className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="font-semibold">No Day Open</h3>
                            <p className="text-sm text-muted-foreground">
                                Select a date and click &quot;Open Day&quot; to start
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Day Summary Stats */}
            {isDayOpen && currentDay && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Total Sales"
                        value={formatMoney(currentDay.total_sales || 0)}
                        className="bg-blue-50 border-blue-200"
                    />
                    <StatCard
                        title="Total Collections"
                        value={formatMoney(currentDay.total_collections || 0)}
                        className="bg-green-50 border-green-200"
                    />
                    <StatCard
                        title="Total Expenses"
                        value={formatMoney(currentDay.total_expenses || 0)}
                        className="bg-orange-50 border-orange-200"
                    />
                </div>
            )}

            {/* Close Day Confirmation */}
            <ConfirmDialog
                open={showCloseConfirm}
                onOpenChange={setShowCloseConfirm}
                title="Close Current Day?"
                description="This will finalize all transactions for today. You won't be able to add more transactions after closing."
                confirmLabel="Close Day"
                onConfirm={handleCloseDay}
                variant="destructive"
                loading={closeDay.isPending}
            />
        </div>
    );
}
