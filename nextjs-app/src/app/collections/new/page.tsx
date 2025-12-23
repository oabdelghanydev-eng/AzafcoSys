'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { LoadingState } from '@/components/shared/loading-state';
import { formatMoney } from '@/lib/formatters';
import { useCustomers } from '@/hooks/api/use-customers';
import { useUnpaidInvoices, useCreateCollection } from '@/hooks/api/use-collections';
import { useUIStore } from '@/stores/ui-store';

export default function NewCollectionPage() {
    const router = useRouter();
    const [customerId, setCustomerId] = useState('');
    const [amount, setAmount] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [distributionMethod, setDistributionMethod] = useState('auto');

    // Get working date from store
    const workingDate = useUIStore((state) => state.workingDate);

    // API hooks
    const { data: customersData, isLoading: customersLoading } = useCustomers();
    const { data: unpaidData } = useUnpaidInvoices(customerId ? parseInt(customerId) : undefined);
    const createCollection = useCreateCollection();

    const customers = customersData?.data ?? customersData ?? [];
    const unpaidInvoices = unpaidData?.data ?? unpaidData ?? [];
    const totalUnpaid = Array.isArray(unpaidInvoices)
        ? unpaidInvoices.reduce((sum: number, inv: { balance?: number }) => sum + (inv.balance || 0), 0)
        : 0;

    const handleSubmit = async () => {
        if (!customerId || !amount) {
            toast.error('Please fill all required fields');
            return;
        }

        if (!workingDate) {
            toast.error('Please open a day first');
            return;
        }

        try {
            await createCollection.mutateAsync({
                customer_id: parseInt(customerId),
                date: workingDate,
                amount: parseFloat(amount),
                payment_method: paymentMethod as 'cash' | 'bank',
                distribution_method: distributionMethod as 'auto' | 'manual',
            });
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
                        <Select value={customerId} onValueChange={setCustomerId}>
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
                                {Array.isArray(unpaidInvoices) ? unpaidInvoices.length : 0} unpaid invoice(s)
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
                        <Select value={distributionMethod} onValueChange={setDistributionMethod}>
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
                        disabled={createCollection.isPending || !customerId || !amount}
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
    );
}
