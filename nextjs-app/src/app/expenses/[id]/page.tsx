'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, X, Loader2 as _Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ExpenseTypeBadge, PaymentMethodBadge } from '@/components/shared/status-badges';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useExpense, useCancelExpense } from '@/hooks/api/use-expenses';
import type { Expense } from '@/types/api';

export default function ExpenseDetailPage() {
    const params = useParams();
    const _router = useRouter();
    const id = Number(params.id);
    const [showCancelDialog, setShowCancelDialog] = useState(false);

    const { data: expenseData, isLoading, error, refetch } = useExpense(id);
    const cancelExpense = useCancelExpense();

    // Extract the actual expense data from API response
    const expense = (expenseData as Expense) || expenseData;

    const handleCancel = async () => {
        try {
            await cancelExpense.mutateAsync(id);
            toast.success('Expense cancelled');
            setShowCancelDialog(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to cancel');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading expense..." />;
    }

    if (error || !expense) {
        return (
            <ErrorState
                title="Failed to load expense"
                message="Could not fetch expense details"
                retry={() => refetch()}
            />
        );
    }

    const canCancel = expense.status !== 'cancelled';

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/expenses">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold">{expense.expense_number || `Expense #${expense.id}`}</h1>
                            <Badge variant={expense.status === 'completed' ? 'default' : 'secondary'}>
                                {expense.status}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">
                            {formatDateShort(expense.date)}
                        </p>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <PermissionGate permission="expenses.cancel">
                        {canCancel && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowCancelDialog(true)}
                                disabled={cancelExpense.isPending}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </PermissionGate>
                </div>
            </div>

            {/* Details Card */}
            <Card>
                <CardHeader>
                    <CardTitle>Expense Details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-sm text-muted-foreground">Type</p>
                        <ExpenseTypeBadge type={expense.type} />
                    </div>
                    {expense.supplier && (
                        <div>
                            <p className="text-sm text-muted-foreground">Supplier</p>
                            <p className="font-medium">{expense.supplier.name}</p>
                        </div>
                    )}
                    <div>
                        <p className="text-sm text-muted-foreground">Date</p>
                        <p className="font-medium">{formatDateShort(expense.date)}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Amount</p>
                        <p className="font-bold text-lg text-red-600 money">
                            {formatMoney(expense.amount)}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Payment Method</p>
                        <PaymentMethodBadge method={expense.payment_method} />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Status</p>
                        <Badge variant={expense.status === 'completed' ? 'default' : 'secondary'}>
                            {expense.status}
                        </Badge>
                    </div>
                    {expense.description && (
                        <div className="sm:col-span-2">
                            <p className="text-sm text-muted-foreground">Description</p>
                            <p className="font-medium">{expense.description}</p>
                        </div>
                    )}
                    {expense.notes && (
                        <div className="sm:col-span-2">
                            <p className="text-sm text-muted-foreground">Notes</p>
                            <p className="text-sm">{expense.notes}</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Confirm Cancel Dialog */}
            <ConfirmDialog
                open={showCancelDialog}
                onOpenChange={setShowCancelDialog}
                title="Cancel Expense"
                description="Are you sure you want to cancel this expense? This action cannot be undone."
                confirmLabel="Cancel Expense"
                onConfirm={handleCancel}
                loading={cancelExpense.isPending}
                variant="destructive"
            />
        </div>
    );
}
