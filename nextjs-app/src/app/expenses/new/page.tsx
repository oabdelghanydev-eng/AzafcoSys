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
import { useSuppliers } from '@/hooks/api/use-suppliers';
import { useCreateExpense } from '@/hooks/api/use-expenses';
import { useUIStore } from '@/stores/ui-store';

export default function NewExpensePage() {
    const router = useRouter();
    const [type, setType] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [amount, setAmount] = useState('');
    const [description, setDescription] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');

    // Get working date from store
    const workingDate = useUIStore((state) => state.workingDate);

    // API hooks
    const { data: suppliersData, isLoading: suppliersLoading } = useSuppliers();
    const createExpense = useCreateExpense();

    const suppliers = suppliersData?.data ?? suppliersData ?? [];
    const showSupplierSelect = type === 'supplier' || type === 'supplier_payment';

    // Auto-select first supplier for faster data entry when supplier is needed
    if (showSupplierSelect && !supplierId && Array.isArray(suppliers) && suppliers.length > 0) {
        setSupplierId(suppliers[0].id.toString());
    }

    const handleSubmit = async () => {
        if (!type || !amount || !description) {
            toast.error('Please fill all required fields');
            return;
        }

        if (showSupplierSelect && !supplierId) {
            toast.error('Please select a supplier');
            return;
        }

        if (!workingDate) {
            toast.error('Please open a day first');
            return;
        }

        try {
            await createExpense.mutateAsync({
                date: workingDate,
                type: type as 'company' | 'supplier' | 'supplier_payment',
                supplier_id: showSupplierSelect ? parseInt(supplierId) : undefined,
                amount: parseFloat(amount),
                description,
                payment_method: paymentMethod as 'cash' | 'bank',
            });
            toast.success('Expense recorded successfully');
            router.push('/expenses');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to record expense');
        }
    };

    if (suppliersLoading) {
        return <LoadingState message="Loading..." />;
    }

    return (
        <div className="space-y-6 pb-24">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" asChild>
                    <Link href="/expenses">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-2xl font-bold">New Expense</h1>
                    <p className="text-muted-foreground">Record a new expense</p>
                </div>
            </div>

            {/* Form */}
            <Card>
                <CardHeader>
                    <CardTitle>Expense Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Type */}
                    <div className="space-y-2">
                        <Label>Expense Type *</Label>
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="touch-target">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="company">Company Expense</SelectItem>
                                <SelectItem value="supplier">Supplier Expense</SelectItem>
                                <SelectItem value="supplier_payment">Supplier Payment</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Supplier (conditional) */}
                    {showSupplierSelect && (
                        <div className="space-y-2">
                            <Label>Supplier *</Label>
                            <Select value={supplierId} onValueChange={setSupplierId}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue placeholder="Select supplier" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.isArray(suppliers) && suppliers.map((s: { id: number; name: string }) => (
                                        <SelectItem key={s.id} value={s.id.toString()}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Description */}
                    <div className="space-y-2">
                        <Label>Description *</Label>
                        <Input
                            placeholder="e.g. Fuel, Office supplies"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            className="touch-target"
                        />
                    </div>

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
                </CardContent>
            </Card>

            {/* Sticky Bottom */}
            <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Amount</p>
                        <p className="text-2xl font-bold text-red-600 money">
                            {formatMoney(parseFloat(amount) || 0)}
                        </p>
                    </div>
                    <Button
                        size="lg"
                        onClick={handleSubmit}
                        disabled={createExpense.isPending || !type || !amount || !description}
                        className="touch-target px-8"
                    >
                        {createExpense.isPending ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Save Expense'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
