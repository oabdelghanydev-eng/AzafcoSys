'use client';

import { useState, useEffect } from 'react';
import { Loader2, AlertCircle } from 'lucide-react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { formatMoney } from '@/lib/formatters';
import { usePriceAdjustment, type PriceAdjustmentItem } from '@/hooks/api/use-invoices';

interface InvoiceItem {
    product?: { name?: string };
    quantity: number;
    unit_price: number;
    subtotal: number;
}

interface PriceAdjustmentModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    invoiceId: number;
    invoiceNumber: string;
    items: InvoiceItem[];
    onSuccess?: () => void;
}

interface AdjustmentRow {
    productName: string;
    quantity: number;
    oldPrice: number;
    newPrice: string;
}

export function PriceAdjustmentModal({
    open,
    onOpenChange,
    invoiceId,
    invoiceNumber,
    items,
    onSuccess,
}: PriceAdjustmentModalProps) {
    const [adjustments, setAdjustments] = useState<AdjustmentRow[]>([]);
    const priceAdjustment = usePriceAdjustment();

    // Initialize adjustments from invoice items
    useEffect(() => {
        if (open && items.length > 0) {
            setAdjustments(
                items.map(item => ({
                    productName: item.product?.name || 'Product',
                    quantity: item.quantity,
                    oldPrice: item.unit_price,
                    newPrice: item.unit_price.toString(),
                }))
            );
        }
    }, [open, items]);

    const handlePriceChange = (index: number, value: string) => {
        const newAdjustments = [...adjustments];
        newAdjustments[index].newPrice = value;
        setAdjustments(newAdjustments);
    };

    const calculateDifference = (row: AdjustmentRow): number => {
        const newPrice = parseFloat(row.newPrice) || 0;
        return (newPrice - row.oldPrice) * row.quantity;
    };

    const getTotalAdjustment = (): number => {
        return adjustments.reduce((sum, row) => sum + calculateDifference(row), 0);
    };

    const getAdjustedItems = (): PriceAdjustmentItem[] => {
        return adjustments
            .filter(row => {
                const newPrice = parseFloat(row.newPrice) || 0;
                return newPrice !== row.oldPrice;
            })
            .map(row => ({
                product_name: row.productName,
                old_price: row.oldPrice,
                new_price: parseFloat(row.newPrice) || 0,
                quantity: row.quantity,
            }));
    };

    const handleSubmit = async () => {
        const adjustedItems = getAdjustedItems();

        if (adjustedItems.length === 0) {
            toast.error('No price changes detected');
            return;
        }

        try {
            await priceAdjustment.mutateAsync({
                invoiceId,
                adjustments: adjustedItems,
            });
            toast.success('Price adjustment created successfully');
            onOpenChange(false);
            onSuccess?.();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to create price adjustment');
        }
    };

    const totalAdjustment = getTotalAdjustment();
    const hasChanges = getAdjustedItems().length > 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Price Adjustment</DialogTitle>
                    <DialogDescription>
                        Adjust prices for invoice {invoiceNumber}. This will create a credit or debit note.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead className="text-right">Qty (kg)</TableHead>
                                <TableHead className="text-right">Old Price</TableHead>
                                <TableHead className="text-right">New Price</TableHead>
                                <TableHead className="text-right">Difference</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {adjustments.map((row, index) => {
                                const diff = calculateDifference(row);
                                return (
                                    <TableRow key={index}>
                                        <TableCell className="font-medium">{row.productName}</TableCell>
                                        <TableCell className="text-right">{row.quantity.toLocaleString()}</TableCell>
                                        <TableCell className="text-right money">{formatMoney(row.oldPrice)}</TableCell>
                                        <TableCell className="text-right">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={row.newPrice}
                                                onChange={(e) => handlePriceChange(index, e.target.value)}
                                                className="w-24 text-right"
                                            />
                                        </TableCell>
                                        <TableCell className={`text-right font-semibold ${diff > 0 ? 'text-green-600' : diff < 0 ? 'text-red-600' : ''}`}>
                                            {diff !== 0 ? formatMoney(diff) : '-'}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    {hasChanges && (
                        <Alert variant={totalAdjustment > 0 ? 'default' : 'destructive'}>
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                {totalAdjustment > 0 ? (
                                    <>Customer will owe an <strong>additional {formatMoney(totalAdjustment)}</strong> (Debit Note)</>
                                ) : (
                                    <>Customer balance will <strong>decrease by {formatMoney(Math.abs(totalAdjustment))}</strong> (Credit Note)</>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={!hasChanges || priceAdjustment.isPending}
                    >
                        {priceAdjustment.isPending ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Processing...
                            </>
                        ) : (
                            'Create Adjustment'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
