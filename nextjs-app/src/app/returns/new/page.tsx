'use client';

import { useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Plus, Trash2, Loader2, AlertCircle, FileText } from 'lucide-react';
import { toast } from 'sonner';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { LoadingState } from '@/components/shared/loading-state';
import { RequireOpenDay } from '@/components/shared/require-open-day';
import { formatMoney, formatQuantity, formatDateShort } from '@/lib/formatters';
import { useCustomers } from '@/hooks/api/use-customers';
import { useInvoices, useInvoice } from '@/hooks/api/use-invoices';
import { useCreateReturn } from '@/hooks/api/use-returns';
import type { InvoiceItem } from '@/types/api';

// Return item derived from invoice item
interface ReturnItemEntry {
    invoiceItemId: number;
    product_id: number;
    product_name: string;
    // Original invoice values (for validation)
    originalCartons: number;
    originalWeight: number;
    originalPrice: number;
    // Return values (capped at original)
    cartons: number;
    weight: number;
    price: number; // Always equals originalPrice (read-only)
}

export default function NewReturnPage() {
    const router = useRouter();

    // Step 1: Customer selection
    const [customerId, setCustomerId] = useState('');

    // Step 2: Invoice selection (required)
    const [invoiceId, setInvoiceId] = useState('');

    // Step 3: Items from invoice
    const [items, setItems] = useState<ReturnItemEntry[]>([]);

    // Currently selected invoice item to add
    const [selectedInvoiceItemId, setSelectedInvoiceItemId] = useState('');
    const [returnCartons, setReturnCartons] = useState('');
    const [returnWeight, setReturnWeight] = useState('');

    // API hooks
    const { data: customersData, isLoading: customersLoading } = useCustomers();
    const { data: invoicesData, isLoading: invoicesLoading } = useInvoices(
        customerId ? { customer_id: parseInt(customerId) } : undefined
    );
    const { data: invoiceDetail, isLoading: invoiceDetailLoading } = useInvoice(
        invoiceId ? parseInt(invoiceId) : 0
    );
    const createReturn = useCreateReturn();

    const customers = customersData?.data ?? customersData ?? [];
    const invoicesRaw = invoicesData?.data ?? invoicesData ?? [];
    const customerInvoices = Array.isArray(invoicesRaw) ? invoicesRaw.filter((inv: { status: string }) => inv.status !== 'cancelled') : [];

    // Get invoice items from selected invoice
    const invoiceItems: InvoiceItem[] = invoiceDetail?.items ?? [];

    // Calculate what's available to return (original qty minus already added)
    const availableItems = useMemo(() => {
        return invoiceItems.map(item => {
            const alreadyAddedWeight = items
                .filter(i => i.invoiceItemId === item.id)
                .reduce((sum, i) => sum + i.weight, 0);
            const alreadyAddedCartons = items
                .filter(i => i.invoiceItemId === item.id)
                .reduce((sum, i) => sum + i.cartons, 0);

            return {
                ...item,
                remainingWeight: item.quantity - alreadyAddedWeight,
                remainingCartons: item.cartons - alreadyAddedCartons,
            };
        }).filter(item => item.remainingWeight > 0 || item.remainingCartons > 0);
    }, [invoiceItems, items]);

    // Handle customer change - reset invoice and items
    const handleCustomerChange = (value: string) => {
        setCustomerId(value);
        setInvoiceId('');
        setItems([]);
        setSelectedInvoiceItemId('');
    };

    // Handle invoice change - reset items
    const handleInvoiceChange = (value: string) => {
        setInvoiceId(value);
        setItems([]);
        setSelectedInvoiceItemId('');
    };

    const handleAddItem = () => {
        if (!selectedInvoiceItemId || !returnCartons || !returnWeight) {
            toast.error('Please fill all item fields');
            return;
        }

        const invoiceItem = invoiceItems.find(i => i.id.toString() === selectedInvoiceItemId);
        if (!invoiceItem) {
            toast.error('Invalid invoice item');
            return;
        }

        const cartonsNum = parseInt(returnCartons);
        const weightNum = parseFloat(returnWeight);

        // Get available amounts
        const available = availableItems.find(i => i.id.toString() === selectedInvoiceItemId);
        if (!available) {
            toast.error('This item has been fully returned');
            return;
        }

        // Validate against remaining available
        if (cartonsNum > available.remainingCartons) {
            toast.error(`Maximum cartons available: ${available.remainingCartons}`);
            return;
        }
        if (weightNum > available.remainingWeight) {
            toast.error(`Maximum weight available: ${formatQuantity(available.remainingWeight)} kg`);
            return;
        }

        if (cartonsNum <= 0 || weightNum <= 0) {
            toast.error('Cartons and weight must be positive');
            return;
        }

        const newItem: ReturnItemEntry = {
            invoiceItemId: invoiceItem.id,
            product_id: invoiceItem.product.id,
            product_name: invoiceItem.product.name,
            originalCartons: invoiceItem.cartons,
            originalWeight: invoiceItem.quantity,
            originalPrice: invoiceItem.unit_price,
            cartons: cartonsNum,
            weight: weightNum,
            price: invoiceItem.unit_price, // Price is always from invoice
        };

        setItems([...items, newItem]);
        setSelectedInvoiceItemId('');
        setReturnCartons('');
        setReturnWeight('');
        toast.success(`${invoiceItem.product.name} added`);
    };

    const handleRemoveItem = (index: number) => {
        setItems(items.filter((_, i) => i !== index));
    };

    const handleSubmit = async () => {
        if (!customerId || !invoiceId || items.length === 0) {
            toast.error('Please select a customer, invoice, and add items');
            return;
        }

        try {
            await createReturn.mutateAsync({
                customer_id: parseInt(customerId),
                original_invoice_id: parseInt(invoiceId), // Link to original invoice
                items: items.map(item => ({
                    product_id: item.product_id,
                    quantity: item.weight, // Backend expects 'quantity' (weight in kg)
                    unit_price: item.price, // Backend expects 'unit_price'
                })),
            });
            toast.success('Return recorded successfully');
            router.push('/returns');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to record return');
        }
    };

    const totalAmount = items.reduce((sum, item) => sum + (item.weight * item.price), 0);

    if (customersLoading) {
        return <LoadingState message="Loading..." />;
    }

    const selectedInvoice = customerInvoices.find((inv: { id: number }) => inv.id.toString() === invoiceId);

    return (
        <RequireOpenDay>
            <div className="space-y-6 pb-24">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/returns">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">New Return</h1>
                        <p className="text-muted-foreground">Record a customer return linked to original invoice</p>
                    </div>
                </div>

                {/* Safety Notice */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Returns must be linked to the original invoice. Quantities and prices are derived from the invoice to prevent discrepancies.
                    </AlertDescription>
                </Alert>

                {/* Step 1: Customer */}
                <Card>
                    <CardHeader>
                        <CardTitle>Step 1: Customer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <Label>Select Customer *</Label>
                            <Select value={customerId} onValueChange={handleCustomerChange}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue placeholder="Select customer" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.isArray(customers) && customers.map((c: { id: number; name: string }) => (
                                        <SelectItem key={c.id} value={c.id.toString()}>
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Step 2: Invoice Selection */}
                {customerId && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Step 2: Original Invoice
                            </CardTitle>
                            <CardDescription>
                                Select the invoice for this return
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {invoicesLoading ? (
                                <p className="text-sm text-muted-foreground">Loading invoices...</p>
                            ) : customerInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No invoices found for this customer</p>
                            ) : (
                                <div className="space-y-2">
                                    <Label>Select Invoice *</Label>
                                    <Select value={invoiceId} onValueChange={handleInvoiceChange}>
                                        <SelectTrigger className="touch-target">
                                            <SelectValue placeholder="Select invoice" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {customerInvoices.map((inv: { id: number; invoice_number: string; date: string; total: number }) => (
                                                <SelectItem key={inv.id} value={inv.id.toString()}>
                                                    {inv.invoice_number} - {formatDateShort(inv.date)} ({formatMoney(inv.total)})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Step 3: Add Items from Invoice */}
                {invoiceId && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 3: Return Items</CardTitle>
                            <CardDescription>
                                Select items from invoice to return. Prices are fixed from the original sale.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {invoiceDetailLoading ? (
                                <p className="text-sm text-muted-foreground">Loading invoice items...</p>
                            ) : availableItems.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {items.length > 0 ? 'All invoice items have been added' : 'No items available for return'}
                                </p>
                            ) : (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1 sm:col-span-2">
                                            <Label className="text-xs">Invoice Item</Label>
                                            <Select value={selectedInvoiceItemId} onValueChange={setSelectedInvoiceItemId}>
                                                <SelectTrigger className="touch-target">
                                                    <SelectValue placeholder="Select item from invoice" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableItems.map((item) => (
                                                        <SelectItem key={item.id} value={item.id.toString()}>
                                                            {item.product.name} - Max: {item.remainingCartons} ctns / {formatQuantity(item.remainingWeight)} kg @ {formatMoney(item.unit_price)}/kg
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">
                                                Cartons (max: {availableItems.find(i => i.id.toString() === selectedInvoiceItemId)?.remainingCartons ?? '-'})
                                            </Label>
                                            <Input
                                                type="number"
                                                inputMode="numeric"
                                                min="1"
                                                max={availableItems.find(i => i.id.toString() === selectedInvoiceItemId)?.remainingCartons ?? undefined}
                                                value={returnCartons}
                                                onChange={(e) => setReturnCartons(e.target.value)}
                                                className="touch-target"
                                                disabled={!selectedInvoiceItemId}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">
                                                Weight kg (max: {formatQuantity(availableItems.find(i => i.id.toString() === selectedInvoiceItemId)?.remainingWeight ?? 0)})
                                            </Label>
                                            <Input
                                                type="number"
                                                inputMode="decimal"
                                                step="0.01"
                                                min="0.01"
                                                max={availableItems.find(i => i.id.toString() === selectedInvoiceItemId)?.remainingWeight ?? undefined}
                                                value={returnWeight}
                                                onChange={(e) => setReturnWeight(e.target.value)}
                                                className="touch-target"
                                                disabled={!selectedInvoiceItemId}
                                            />
                                        </div>
                                        {selectedInvoiceItemId && (
                                            <div className="sm:col-span-2 p-3 bg-muted/50 rounded-lg">
                                                <p className="text-sm">
                                                    <span className="text-muted-foreground">Price per KG (from invoice): </span>
                                                    <span className="font-semibold">
                                                        {formatMoney(availableItems.find(i => i.id.toString() === selectedInvoiceItemId)?.unit_price ?? 0)}
                                                    </span>
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                    <Button
                                        onClick={handleAddItem}
                                        variant="outline"
                                        className="w-full touch-target"
                                        disabled={!selectedInvoiceItemId}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Item
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Items List */}
                {items.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Items ({items.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {items.map((item, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between p-3 rounded-lg bg-muted/50"
                                >
                                    <div>
                                        <p className="font-medium">{item.product_name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.cartons} ctns × {formatQuantity(item.weight)}kg @ {formatMoney(item.price)}/kg
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <p className="font-semibold text-orange-600 money">
                                            {formatMoney(item.weight * item.price)}
                                        </p>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-destructive"
                                            onClick={() => handleRemoveItem(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* Sticky Bottom */}
                <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                    <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Total Return</p>
                            <p className="text-2xl font-bold text-orange-600 money">
                                {formatMoney(totalAmount)}
                            </p>
                        </div>
                        <Button
                            size="lg"
                            onClick={handleSubmit}
                            disabled={createReturn.isPending || !customerId || !invoiceId || items.length === 0}
                            className="touch-target px-8"
                        >
                            {createReturn.isPending ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                'Save Return'
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </RequireOpenDay>
    );
}
