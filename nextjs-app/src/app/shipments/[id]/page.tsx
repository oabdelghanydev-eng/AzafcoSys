'use client';

import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, FileText, Loader2, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PermissionGate } from '@/components/shared/permission-gate';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { ShipmentStatusBadge } from '@/components/shared/status-badges';
import { formatDateShort, formatQuantity, formatInteger } from '@/lib/formatters';
import { useShipment, useShipments, useCloseShipment, useSettleShipment } from '@/hooks/api/use-shipments';
import type { Shipment as ShipmentType, ShipmentItem } from '@/types/api';

export default function ShipmentDetailPage() {
    const params = useParams();
    const id = Number(params.id);
    const [showCloseConfirm, setShowCloseConfirm] = useState(false);
    const [showSettleDialog, setShowSettleDialog] = useState(false);
    const [selectedNextShipmentId, setSelectedNextShipmentId] = useState<string>('');

    const { data: shipment, isLoading, error, refetch } = useShipment(id);
    const { data: shipmentsData } = useShipments();
    const closeShipment = useCloseShipment();
    const settleShipment = useSettleShipment();

    // Get open shipments for carryover (exclude current shipment)
    const openShipments = useMemo(() => {
        const shipments = shipmentsData?.data || [];
        return shipments.filter((s: ShipmentType) => s.status === 'open' && s.id !== id);
    }, [shipmentsData, id]);

    // Check if shipment has remaining cartons
    const hasRemainingCartons = useMemo(() => {
        if (!shipment?.items) return false;
        return shipment.items.some((item: ShipmentItem) =>
            (item.remaining_cartons ?? 0) > 0
        );
    }, [shipment]);

    const handleClose = async () => {
        try {
            await closeShipment.mutateAsync(id);
            toast.success('Shipment closed successfully');
            setShowCloseConfirm(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to close shipment');
        }
    };

    const handleSettle = async () => {
        try {
            // If has remaining and no next shipment selected, show error
            if (hasRemainingCartons && !selectedNextShipmentId) {
                toast.error('Please select a shipment for carryover');
                return;
            }

            await settleShipment.mutateAsync({
                id,
                nextShipmentId: selectedNextShipmentId ? Number(selectedNextShipmentId) : undefined
            });
            toast.success('Shipment settled successfully');
            setShowSettleDialog(false);
            setSelectedNextShipmentId('');
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to settle shipment');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading shipment..." />;
    }

    if (error || !shipment) {
        return (
            <ErrorState
                title="Failed to load shipment"
                message="Shipment not found or could not be loaded"
                retry={() => refetch()}
            />
        );
    }

    const canClose = shipment.status === 'open';
    const canSettle = shipment.status === 'closed';
    const totalWeight = (shipment.items || []).reduce(
        (sum, item) => sum + item.cartons * item.weight_per_unit, 0
    );
    const totalCartons = (shipment.items || []).reduce(
        (sum, item) => sum + item.cartons, 0
    );
    const totalRemaining = (shipment.items || []).reduce(
        (sum, item) => sum + (item.remaining_cartons ?? 0), 0
    );

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/shipments">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">Shipment #{shipment.id}</h1>
                            <ShipmentStatusBadge status={shipment.status} />
                        </div>
                        <p className="text-muted-foreground">{formatDateShort(shipment.date)}</p>
                    </div>
                </div>

                <div className="flex gap-2">
                    {canClose && (
                        <PermissionGate permission="shipments.update">
                            <Button
                                variant="outline"
                                onClick={() => setShowCloseConfirm(true)}
                                disabled={closeShipment.isPending}
                            >
                                {closeShipment.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Close Shipment
                            </Button>
                        </PermissionGate>
                    )}
                    {canSettle && (
                        <PermissionGate permission="shipments.update">
                            <Button
                                onClick={() => setShowSettleDialog(true)}
                                disabled={settleShipment.isPending}
                            >
                                {settleShipment.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Settle Shipment
                            </Button>
                        </PermissionGate>
                    )}
                </div>
            </div>

            {/* Shipment Info */}
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Supplier</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="font-semibold text-lg">{shipment.supplier?.name}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Total Items</span>
                            <span className="font-medium">{shipment.items?.length || 0}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Total Cartons</span>
                            <span className="font-medium">{formatInteger(totalCartons)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Total Weight</span>
                            <span className="font-medium">{formatQuantity(totalWeight)} kg</span>
                        </div>
                        {shipment.status !== 'open' && (
                            <div className="flex justify-between border-t pt-2 mt-2">
                                <span className="text-muted-foreground">Remaining Cartons</span>
                                <span className={`font-medium ${totalRemaining > 0 ? 'text-orange-600' : 'text-green-600'}`}>
                                    {formatInteger(totalRemaining)}
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Items Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Items ({shipment.items?.length || 0})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead className="text-right">Cartons</TableHead>
                                <TableHead className="text-right">Weight/Unit</TableHead>
                                <TableHead className="text-right">Total Weight</TableHead>
                                {shipment.status !== 'open' && (
                                    <TableHead className="text-right">Remaining</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {(shipment.items || []).map((item: ShipmentItem, index: number) => (
                                <TableRow key={index}>
                                    <TableCell className="font-medium">{item.product?.name || 'Product'}</TableCell>
                                    <TableCell className="text-right">{formatInteger(item.cartons)}</TableCell>
                                    <TableCell className="text-right">{formatQuantity(item.weight_per_unit)} kg</TableCell>
                                    <TableCell className="text-right">{formatQuantity(item.cartons * item.weight_per_unit)} kg</TableCell>
                                    {shipment.status !== 'open' && (
                                        <TableCell className="text-right">
                                            <span className={item.remaining_cartons && item.remaining_cartons > 0 ? 'text-orange-600 font-medium' : ''}>
                                                {item.remaining_cartons !== undefined ? formatInteger(item.remaining_cartons) : '-'}
                                            </span>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Close Confirmation */}
            <ConfirmDialog
                open={showCloseConfirm}
                onOpenChange={setShowCloseConfirm}
                title="Close Shipment?"
                description="This will close the shipment. You won't be able to make changes to the items."
                confirmLabel="Close Shipment"
                onConfirm={handleClose}
                loading={closeShipment.isPending}
            />

            {/* Settle Dialog with Carryover Selection */}
            <Dialog open={showSettleDialog} onOpenChange={setShowSettleDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Settle Shipment</DialogTitle>
                        <DialogDescription>
                            {hasRemainingCartons ? (
                                <span className="flex items-center gap-2 text-orange-600">
                                    <AlertTriangle className="h-4 w-4" />
                                    {formatInteger(totalRemaining)} cartons remaining need to be carried over
                                </span>
                            ) : (
                                'This will settle the shipment and record the supplier payment.'
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {hasRemainingCartons && (
                        <div className="py-4">
                            <label className="text-sm font-medium mb-2 block">
                                Select next shipment for carryover
                            </label>
                            {openShipments.length === 0 ? (
                                <div className="text-center py-4 text-muted-foreground">
                                    <p>No open shipments available for carryover</p>
                                    <Button variant="link" asChild className="mt-2">
                                        <Link href="/shipments/new">Create New Shipment</Link>
                                    </Button>
                                </div>
                            ) : (
                                <Select value={selectedNextShipmentId} onValueChange={setSelectedNextShipmentId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select shipment..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {openShipments.map((s: ShipmentType) => (
                                            <SelectItem key={s.id} value={s.id.toString()}>
                                                #{s.id} - {s.supplier?.name} ({formatDateShort(s.date)})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => setShowSettleDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSettle}
                            disabled={settleShipment.isPending || (hasRemainingCartons && (!selectedNextShipmentId || openShipments.length === 0))}
                        >
                            {settleShipment.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {hasRemainingCartons ? 'Settle & Carryover' : 'Settle Shipment'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
