'use client';

import { useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Loader2, AlertCircle, FileText } from 'lucide-react';
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
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { money, sumMoney, moneyEquals } from '@/lib/money';
import { useCustomers } from '@/hooks/api/use-customers';
import { useUnpaidInvoices, useCreateCollection } from '@/hooks/api/use-collections';
import { useUIStore } from '@/stores/ui-store';
import type { Invoice } from '@/types/api';

// Allocation entry for manual distribution
interface AllocationEntry {
    invoice_id: number;
    invoice_number: string;
    invoice_balance: number;
    amount: string; // String for form input, converted to number on submit
}

export default function NewCollectionPage() {
    const router = useRouter();
    const [customerId, setCustomerId] = useState('');
    const [amount, setAmount] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [distributionMethod, setDistributionMethod] = useState('auto');

    // Manual allocation state
    const [allocations, setAllocations] = useState<AllocationEntry[]>([]);

    // Get working date from store
    const workingDate = useUIStore((state) => state.workingDate);

    // API hooks
    const { data: customersData, isLoading: customersLoading } = useCustomers();
    const { data: unpaidData } = useUnpaidInvoices(customerId ? parseInt(customerId) : undefined);
    const createCollection = useCreateCollection();

    const customers = customersData?.data ?? customersData ?? [];
    const unpaidInvoicesRaw = unpaidData?.data ?? unpaidData ?? [];
    const unpaidInvoices: Invoice[] = Array.isArray(unpaidInvoicesRaw) ? unpaidInvoicesRaw : [];
    const totalUnpaid = unpaidInvoices.reduce((sum, inv) => sum + (inv.balance || 0), 0);

    // Calculate allocation sum using decimal-safe arithmetic
    const allocationSum = useMemo(() => {
        const amounts = allocations.map(a => parseFloat(a.amount) || 0);
        return sumMoney(amounts);
    }, [allocations]);

    // Check if allocation matches payment amount (decimal-safe)
    const allocationMatches = useMemo(() => {
        if (distributionMethod !== 'manual') return true;
        const paymentAmount = parseFloat(amount) || 0;
        return moneyEquals(allocationSum.toDecimal(), paymentAmount);
    }, [allocationSum, amount, distributionMethod]);

    const allocationDifference = useMemo(() => {
        const paymentAmount = parseFloat(amount) || 0;
        return money(paymentAmount).minus(allocationSum).toFixed();
    }, [allocationSum, amount]);

    // Handle customer change - reset allocations
    const handleCustomerChange = (value: string) => {
        setCustomerId(value);
        setAllocations([]);
    };

    // Handle distribution method change
    const handleDistributionMethodChange = (value: string) => {
        setDistributionMethod(value);
        if (value === 'manual') {
            // Initialize allocations with all unpaid invoices
            setAllocations(unpaidInvoices.map(inv => ({
                invoice_id: inv.id,
                invoice_number: inv.invoice_number,
                invoice_balance: inv.balance,
                amount: '',
            })));
        } else {
            setAllocations([]);
        }
    };

    // Update single allocation amount
    const handleAllocationChange = (invoiceId: number, newAmount: string) => {
        setAllocations(prev => prev.map(a =>
            a.invoice_id === invoiceId ? { ...a, amount: newAmount } : a
        ));
    };

    // Auto-fill remaining amount to first unfilled invoice
    const handleAutoFillRemaining = () => {
        const remaining = parseFloat(allocationDifference);
        if (remaining <= 0) return;

        // Find first invoice with room for more allocation
        setAllocations(prev => {
            const updated = [...prev];
            for (let i = 0; i < updated.length; i++) {
                const current = parseFloat(updated[i].amount) || 0;
                const available = updated[i].invoice_balance - current;
                if (available > 0) {
                    const toAdd = Math.min(remaining, available);
                    updated[i] = { ...updated[i], amount: (current + toAdd).toFixed(2) };
                    break;
                }
            }
            return updated;
        });
    };

    const handleSubmit = async () => {
        if (!customerId || !amount) {
            toast.error('Please fill all required fields');
            return;
        }

        if (!workingDate) {
            toast.error('Please open a day first');
            return;
        }

        // Validate manual allocation sum
        if (distributionMethod === 'manual' && !allocationMatches) {
            toast.error(`Allocation sum (${formatMoney(allocationSum.toDecimal())}) must equal payment amount (${formatMoney(parseFloat(amount))})`);
            return;
        }

        // Validate individual allocations don't exceed invoice balance
        if (distributionMethod === 'manual') {
            for (const alloc of allocations) {
                const allocAmount = parseFloat(alloc.amount) || 0;
                if (allocAmount > alloc.invoice_balance) {
                    toast.error(`Allocation for ${alloc.invoice_number} exceeds invoice balance`);
                    return;
                }
                if (allocAmount < 0) {
                    toast.error(`Allocation amounts must be positive`);
                    return;
                }
            }
        }

        try {
            // Prepare allocation data for backend
            const allocationData = distributionMethod === 'manual'
                ? allocations
                    .filter(a => parseFloat(a.amount) > 0)
                    .map(a => ({
                        invoice_id: a.invoice_id,
                        amount: parseFloat(a.amount),
                    }))
                : undefined;

            await createCollection.mutateAsync({
                customer_id: parseInt(customerId),
                date: workingDate,
                amount: parseFloat(amount),
                payment_method: paymentMethod as 'cash' | 'bank',
                distribution_method: distributionMethod as 'auto' | 'manual',
                allocations: allocationData,
            } as Parameters<typeof createCollection.mutateAsync>[0]);

            toast.success('Collection recorded successfully');
            router.push('/collections');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to record collection');
        }
    };

    if (customersLoading) {
        return <LoadingState message="Loading..." />;
    }

    return (
        <RequireOpenDay>
            <div className="space-y-6 pb-24">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/collections">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">New Collection</h1>
                        <p className="text-muted-foreground">Record customer payment</p>
                    </div>
                </div>

                {/* Form */}
                <Card>
                    <CardHeader>
                        <CardTitle>Collection Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Customer */}
                        <div className="space-y-2">
                            <Label>Customer *</Label>
                            <Select value={customerId} onValueChange={handleCustomerChange}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue placeholder="Select customer" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.isArray(customers) && customers.map((c: { id: number; name: string; balance: number }) => (
                                        <SelectItem key={c.id} value={c.id.toString()}>
                                            {c.name}
                                            {c.balance > 0 && (
                                                <span className="ml-2 text-orange-600">
                                                    ({formatMoney(c.balance)} due)
                                                </span>
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Customer Balance Info */}
                        {customerId && totalUnpaid > 0 && (
                            <div className="p-4 rounded-lg bg-orange-50 border border-orange-200">
                                <p className="text-sm text-orange-800">
                                    Outstanding Balance: <span className="font-bold">{formatMoney(totalUnpaid)}</span>
                                </p>
                                <p className="text-xs text-orange-600 mt-1">
                                    {unpaidInvoices.length} unpaid invoice(s)
                                </p>
                            </div>
                        )}

                        {/* Amount */}
                        <div className="space-y-2">
                            <Label>Amount *</Label>
                            <Input
                                type="number"
                                inputMode="decimal"
                                placeholder="0.00"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                                className="touch-target text-lg"
                            />
                            {totalUnpaid > 0 && (
                                <Button
                                    variant="link"
                                    className="p-0 h-auto text-xs"
                                    onClick={() => setAmount(totalUnpaid.toString())}
                                >
                                    Pay full balance ({formatMoney(totalUnpaid)})
                                </Button>
                            )}
                        </div>

                        {/* Payment Method */}
                        <div className="space-y-2">
                            <Label>Payment Method</Label>
                            <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="bank">Bank Transfer</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Distribution Method */}
                        <div className="space-y-2">
                            <Label>Distribution Method</Label>
                            <Select value={distributionMethod} onValueChange={handleDistributionMethodChange}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="auto">Auto (FIFO - Oldest First)</SelectItem>
                                    <SelectItem value="manual">Manual Allocation</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Manual Allocation Table */}
                {distributionMethod === 'manual' && customerId && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Allocate to Invoices
                            </CardTitle>
                            <CardDescription>
                                Specify how much to apply to each invoice. Total must equal payment amount.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Allocation Validation Status */}
                            {amount && (
                                <Alert variant={allocationMatches ? 'success' : 'warning'}>
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>
                                        {allocationMatches ? (
                                            'Allocation matches payment amount ✓'
                                        ) : (
                                            <>
                                                Difference: <strong>{formatMoney(parseFloat(allocationDifference))}</strong>
                                                {parseFloat(allocationDifference) > 0 && (
                                                    <Button
                                                        variant="link"
                                                        size="sm"
                                                        className="ml-2 p-0 h-auto"
                                                        onClick={handleAutoFillRemaining}
                                                    >
                                                        Auto-fill remaining
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}

                            {unpaidInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No unpaid invoices for this customer</p>
                            ) : (
                                <div className="space-y-3">
                                    {allocations.map((alloc) => (
                                        <div
                                            key={alloc.invoice_id}
                                            className="flex items-center gap-4 p-3 rounded-lg border bg-muted/30"
                                        >
                                            <div className="flex-1">
                                                <p className="font-medium">{alloc.invoice_number}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Balance: {formatMoney(alloc.invoice_balance)}
                                                </p>
                                            </div>
                                            <div className="w-32">
                                                <Input
                                                    type="number"
                                                    inputMode="decimal"
                                                    step="0.01"
                                                    min="0"
                                                    max={alloc.invoice_balance}
                                                    placeholder="0.00"
                                                    value={alloc.amount}
                                                    onChange={(e) => handleAllocationChange(alloc.invoice_id, e.target.value)}
                                                    className="text-right"
                                                />
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleAllocationChange(
                                                    alloc.invoice_id,
                                                    alloc.invoice_balance.toFixed(2)
                                                )}
                                            >
                                                Full
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Allocation Summary */}
                            <div className="pt-4 border-t flex justify-between items-center">
                                <span className="text-sm text-muted-foreground">Allocated Total:</span>
                                <span className={`font-bold text-lg ${allocationMatches ? 'text-green-600' : 'text-orange-600'}`}>
                                    {formatMoney(allocationSum.toDecimal())}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Sticky Bottom */}
                <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                    <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Amount</p>
                            <p className="text-2xl font-bold text-green-600 money">
                                {formatMoney(parseFloat(amount) || 0)}
                            </p>
                        </div>
                        <Button
                            size="lg"
                            onClick={handleSubmit}
                            disabled={
                                createCollection.isPending ||
                                !customerId ||
                                !amount ||
                                (distributionMethod === 'manual' && !allocationMatches)
                            }
                            className="touch-target px-8"
                        >
                            {createCollection.isPending ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                'Save Collection'
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </RequireOpenDay>
    );
}
