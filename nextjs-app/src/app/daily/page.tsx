'use client';

import { useState } from 'react';
import { Calendar, Download, Play, Square, AlertCircle, Loader2, ShieldAlert } from 'lucide-react';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useUIStore } from '@/stores/ui-store';
import { useCurrentDay, useAvailableDates, useOpenDay, useCloseDay, useForceCloseDay } from '@/hooks/api/use-daily-report';
import { config } from '@/lib/config';

export default function DailyReportPage() {
    const [selectedDate, setSelectedDate] = useState<string>('');
    const [showCloseConfirm, setShowCloseConfirm] = useState(false);
    const [showForceCloseDialog, setShowForceCloseDialog] = useState(false);
    const [forceCloseReason, setForceCloseReason] = useState('');
    const { setWorkingDate } = useUIStore();

    // API hooks - these now return unwrapped data
    const { data: currentDay, isLoading: currentLoading, error: currentError, refetch } = useCurrentDay();
    const { data: availableDates, isLoading: datesLoading } = useAvailableDates();
    const openDay = useOpenDay();
    const closeDay = useCloseDay();
    const forceCloseDay = useForceCloseDay();

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

    const handleForceCloseDay = async () => {
        if (!forceCloseReason.trim()) {
            toast.error('Reason is required for force close');
            return;
        }

        try {
            await forceCloseDay.mutateAsync({ reason: forceCloseReason.trim() });
            setWorkingDate(null);
            toast.success('Day force closed successfully');
            setShowForceCloseDialog(false);
            setForceCloseReason('');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to force close day');
        }
    };

    const handleDownloadPDF = async () => {
        if (!currentDay?.date) return;

        // Extract just YYYY-MM-DD from the date
        const dateStr = typeof currentDay.date === 'string'
            ? currentDay.date.split('T')[0]
            : new Date(currentDay.date).toISOString().split('T')[0];

        try {
            // Get auth token from localStorage
            const storage = localStorage.getItem('auth-storage');
            const token = storage ? JSON.parse(storage)?.state?.token : null;

            const response = await fetch(`${config.apiUrl}/reports/daily/${dateStr}/pdf`, {
                headers: {
                    'Authorization': token ? `Bearer ${token}` : '',
                    'Accept': 'application/pdf',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error('Failed to download PDF');
            }

            // Create blob URL and trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `daily-report-${dateStr}.pdf`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            toast.error('Failed to download PDF');
        }
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

                                {/* Admin Force Close - for when normal close fails */}
                                <PermissionGate permission="admin.force_close">
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        onClick={() => setShowForceCloseDialog(true)}
                                        disabled={forceCloseDay.isPending}
                                        className="touch-target"
                                        title="Force Close (Admin)"
                                    >
                                        <ShieldAlert className="h-4 w-4" />
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

            {/* Force Close Dialog - Admin Only */}
            <Dialog open={showForceCloseDialog} onOpenChange={setShowForceCloseDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-destructive">
                            <ShieldAlert className="h-5 w-5" />
                            Force Close Day
                        </DialogTitle>
                        <DialogDescription>
                            Use this when normal close fails due to validation errors.
                            This action will be logged for audit purposes.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 pt-4">
                        <div className="space-y-2">
                            <Label>Reason (Required)</Label>
                            <Textarea
                                placeholder="Explain why force close is necessary..."
                                value={forceCloseReason}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setForceCloseReason(e.target.value)}
                                rows={3}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowForceCloseDialog(false);
                                    setForceCloseReason('');
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleForceCloseDay}
                                disabled={forceCloseDay.isPending || !forceCloseReason.trim()}
                            >
                                {forceCloseDay.isPending ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Closing...
                                    </>
                                ) : (
                                    'Force Close'
                                )}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
