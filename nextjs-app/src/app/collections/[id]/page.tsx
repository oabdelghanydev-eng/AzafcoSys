'use client';

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, X } from 'lucide-react';
import { toast } from 'sonner';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useCollection, useCancelCollection } from '@/hooks/api/use-collections';
import type { Collection, CollectionAllocation, PaymentMethod } from '@/types/api';

function getPaymentMethodBadge(method: PaymentMethod) {
    const styles: Record<PaymentMethod, string> = {
        cash: 'bg-green-100 text-green-800',
        bank: 'bg-blue-100 text-blue-800',
    };
    return (
        <Badge variant="outline" className={styles[method] || ''}>
            {method === 'bank' ? 'Bank Transfer' : 'Cash'}
        </Badge>
    );
}

export default function CollectionDetailPage() {
    const params = useParams();
    const id = Number(params.id);
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);

    const { data: collectionData, isLoading, error, refetch } = useCollection(id);
    const cancelCollection = useCancelCollection();

    // Extract the actual collection data from API response
    const collection = (collectionData as { data?: Collection })?.data || collectionData as Collection | undefined;

    const handleCancel = async () => {
        try {
            await cancelCollection.mutateAsync(id);
            toast.success('Collection cancelled');
            setShowCancelConfirm(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to cancel');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading collection..." />;
    }

    if (error || !collection) {
        return (
            <ErrorState
                title="Failed to load collection"
                message="Could not fetch collection details"
                retry={() => refetch()}
            />
        );
    }

    const canCancel = collection.status !== 'cancelled';

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/collections">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold">{collection.receipt_number}</h1>
                            <Badge variant={collection.status === 'completed' ? 'default' : 'secondary'}>
                                {collection.status}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">
                            {formatDateShort(collection.date)}
                        </p>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <PermissionGate permission="collections.cancel">
                        {canCancel && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowCancelConfirm(true)}
                                disabled={cancelCollection.isPending}
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
                    <CardTitle>Collection Details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-sm text-muted-foreground">Customer</p>
                        <p className="font-medium">{collection.customer?.name}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Date</p>
                        <p className="font-medium">{formatDateShort(collection.date)}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Amount</p>
                        <p className="font-bold text-lg text-green-600 money">
                            {formatMoney(collection.amount)}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Payment Method</p>
                        {getPaymentMethodBadge(collection.payment_method)}
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Distribution</p>
                        <p className="font-medium capitalize">{collection.distribution_method}</p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Status</p>
                        <Badge variant={collection.status === 'completed' ? 'default' : 'secondary'}>
                            {collection.status}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            {/* Allocations */}
            {collection.allocations && collection.allocations.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Allocations</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice #</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {collection.allocations.map((alloc: CollectionAllocation, index: number) => (
                                    <TableRow key={index}>
                                        <TableCell>
                                            <Link
                                                href={`/invoices/${alloc.invoice_id}`}
                                                className="hover:underline text-primary"
                                            >
                                                {alloc.invoice_number || `#${alloc.invoice_id}`}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right font-medium money">
                                            {formatMoney(alloc.amount)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {/* Cancel Confirmation Dialog */}
            <ConfirmDialog
                open={showCancelConfirm}
                onOpenChange={setShowCancelConfirm}
                title="Cancel Collection?"
                description="This will reverse the collection and update invoice balances. This action cannot be undone."
                confirmLabel="Cancel Collection"
                onConfirm={handleCancel}
                variant="destructive"
                loading={cancelCollection.isPending}
            />
        </div>
    );
}
