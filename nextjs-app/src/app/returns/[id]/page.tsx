'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, X, Loader2 as _Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ReturnStatusBadge } from '@/components/shared/status-badges';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useReturn, useCancelReturn } from '@/hooks/api/use-returns';
import type { Return, ReturnItem } from '@/types/api';

export default function ReturnDetailPage() {
    const params = useParams();
    const _router = useRouter();
    const id = Number(params.id);
    const [showCancelDialog, setShowCancelDialog] = useState(false);

    const { data: returnData, isLoading, error, refetch } = useReturn(id);
    const cancelReturn = useCancelReturn();

    // Extract the actual return data from API response
    const returnOrder = (returnData as Return) || returnData;

    const handleCancel = async () => {
        try {
            await cancelReturn.mutateAsync(id);
            toast.success('Return cancelled');
            setShowCancelDialog(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to cancel');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading return..." />;
    }

    if (error || !returnOrder) {
        return (
            <ErrorState
                title="Failed to load return"
                message="Could not fetch return details"
                retry={() => refetch()}
            />
        );
    }

    const canCancel = returnOrder.status !== 'cancelled';
    const items = returnOrder.items || [];

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/returns">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold">
                                {returnOrder.return_number || `Return #${returnOrder.id}`}
                            </h1>
                            <ReturnStatusBadge status={returnOrder.status} />
                        </div>
                        <p className="text-muted-foreground">
                            {formatDateShort(returnOrder.date)}
                        </p>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <PermissionGate permission="returns.cancel">
                        {canCancel && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowCancelDialog(true)}
                                disabled={cancelReturn.isPending}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </PermissionGate>
                </div>
            </div>

            {/* Customer Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Customer</CardTitle>
                </CardHeader>
                <CardContent>
                    {returnOrder.customer ? (
                        <div className="flex justify-between items-center">
                            <div>
                                <p className="font-medium">{returnOrder.customer.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {returnOrder.customer.customer_code}
                                </p>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/customers/${returnOrder.customer.id}`}>
                                    View Customer
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground">No customer</p>
                    )}
                </CardContent>
            </Card>

            {/* Summary */}
            <Card>
                <CardHeader>
                    <CardTitle>Summary</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-sm text-muted-foreground">Date</p>
                        <p className="font-medium">{formatDateShort(returnOrder.date)}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Status</p>
                        <ReturnStatusBadge status={returnOrder.status} />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Total Amount</p>
                        <p className="font-bold text-lg text-red-600 money">
                            {formatMoney(returnOrder.total_amount)}
                        </p>
                    </div>
                    {returnOrder.invoice && (
                        <div>
                            <p className="text-sm text-muted-foreground">Original Invoice</p>
                            <Button variant="link" className="p-0 h-auto" asChild>
                                <Link href={`/invoices/${returnOrder.invoice.id}`}>
                                    {returnOrder.invoice.invoice_number}
                                </Link>
                            </Button>
                        </div>
                    )}
                    {returnOrder.notes && (
                        <div className="sm:col-span-2">
                            <p className="text-sm text-muted-foreground">Notes</p>
                            <p className="text-sm">{returnOrder.notes}</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Items */}
            <Card>
                <CardHeader>
                    <CardTitle>Items ({items.length})</CardTitle>
                </CardHeader>
                <CardContent>
                    {items.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead className="text-right">Qty</TableHead>
                                    <TableHead className="text-right">Weight</TableHead>
                                    <TableHead className="text-right">Price/KG</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item: ReturnItem, index: number) => (
                                    <TableRow key={item.id || index}>
                                        <TableCell className="font-medium">
                                            {item.product?.name || 'Product'}
                                        </TableCell>
                                        <TableCell className="text-right">{item.quantity}</TableCell>
                                        <TableCell className="text-right">{item.weight} KG</TableCell>
                                        <TableCell className="text-right money">
                                            {formatMoney(item.unit_price)}
                                        </TableCell>
                                        <TableCell className="text-right money font-medium">
                                            {formatMoney(item.total)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-center text-muted-foreground py-8">No items</p>
                    )}
                </CardContent>
            </Card>

            {/* Confirm Cancel Dialog */}
            <ConfirmDialog
                open={showCancelDialog}
                onOpenChange={setShowCancelDialog}
                title="Cancel Return"
                description="Are you sure you want to cancel this return? This action cannot be undone."
                confirmLabel="Cancel Return"
                onConfirm={handleCancel}
                loading={cancelReturn.isPending}
                variant="destructive"
            />
        </div>
    );
}
