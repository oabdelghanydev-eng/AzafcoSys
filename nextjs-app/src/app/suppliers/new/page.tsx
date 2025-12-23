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
import { useCreateSupplier } from '@/hooks/api/use-suppliers';

export default function NewSupplierPage() {
    const router = useRouter();
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [openingBalance, setOpeningBalance] = useState('');

    const createSupplier = useCreateSupplier();

    const handleSubmit = async () => {
        if (!name) {
            toast.error('Supplier name is required');
            return;
        }

        try {
            await createSupplier.mutateAsync({
                name,
                phone: phone || undefined,
                opening_balance: openingBalance ? parseFloat(openingBalance) : 0,
            });
            toast.success('Supplier created successfully');
            router.push('/suppliers');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to create supplier');
        }
    };

    return (
        <div className="space-y-6 pb-24 max-w-2xl">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" asChild>
                    <Link href="/suppliers">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-2xl font-bold">New Supplier</h1>
                    <p className="text-muted-foreground">Add a new supplier</p>
                </div>
            </div>

            {/* Form */}
            <Card>
                <CardHeader>
                    <CardTitle>Supplier Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label>Name *</Label>
                        <Input
                            placeholder="Supplier name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="touch-target"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Phone</Label>
                        <Input
                            type="tel"
                            inputMode="tel"
                            placeholder="Phone number"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            className="touch-target"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Opening Balance</Label>
                        <Input
                            type="number"
                            inputMode="decimal"
                            placeholder="0.00"
                            value={openingBalance}
                            onChange={(e) => setOpeningBalance(e.target.value)}
                            className="touch-target"
                        />
                        <p className="text-xs text-muted-foreground">
                            Positive = you owe supplier
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Sticky Bottom */}
            <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                <div className="max-w-2xl mx-auto">
                    <Button
                        size="lg"
                        onClick={handleSubmit}
                        disabled={createSupplier.isPending || !name}
                        className="w-full touch-target"
                    >
                        {createSupplier.isPending ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Create Supplier'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
