'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { useSupplier, useUpdateSupplier } from '@/hooks/api/use-suppliers';
import type { UpdateSupplierData } from '@/types/api';

export default function EditSupplierPage() {
    const params = useParams();
    const router = useRouter();
    const id = Number(params.id);

    const { data: supplier, isLoading, error, refetch } = useSupplier(id);
    const updateSupplier = useUpdateSupplier();

    // Use supplier data directly for initial form values
    const initialName = supplier?.name || '';
    const initialPhone = supplier?.phone || '';
    const initialAddress = supplier?.address || '';
    const initialIsActive = supplier?.is_active ?? true;

    const [name, setName] = useState(initialName);
    const [phone, setPhone] = useState(initialPhone);
    const [address, setAddress] = useState(initialAddress);
    const [isActive, setIsActive] = useState(initialIsActive);
    const [isInitialized, setIsInitialized] = useState(false);

    // Initialize form when data first arrives
    if (supplier && !isInitialized) {
        setName(supplier.name || '');
        setPhone(supplier.phone || '');
        setAddress(supplier.address || '');
        setIsActive(supplier.is_active ?? true);
        setIsInitialized(true);
    }

    const handleSubmit = async () => {
        if (!name) {
            toast.error('Supplier name is required');
            return;
        }

        try {
            const data: UpdateSupplierData = {
                name,
                phone: phone || undefined,
                address: address || undefined,
                is_active: isActive,
            };
            await updateSupplier.mutateAsync({ id, data });
            toast.success('Supplier updated successfully');
            router.push(`/suppliers/${id}`);
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to update supplier');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading supplier..." />;
    }

    if (error || !supplier) {
        return (
            <ErrorState
                title="Failed to load supplier"
                message="Supplier not found"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6 pb-24 max-w-2xl">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" asChild>
                    <Link href={`/suppliers/${id}`}>
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-2xl font-bold">Edit Supplier</h1>
                    <p className="text-muted-foreground">{supplier.supplier_code}</p>
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
                        <Label>Address</Label>
                        <Input
                            placeholder="Address"
                            value={address}
                            onChange={(e) => setAddress(e.target.value)}
                            className="touch-target"
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <Label>Active Status</Label>
                            <p className="text-xs text-muted-foreground">Inactive suppliers won't appear in lists</p>
                        </div>
                        <Switch
                            checked={isActive}
                            onCheckedChange={setIsActive}
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Sticky Bottom */}
            <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                <div className="max-w-2xl mx-auto">
                    <Button
                        size="lg"
                        onClick={handleSubmit}
                        disabled={updateSupplier.isPending || !name}
                        className="w-full touch-target"
                    >
                        {updateSupplier.isPending ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Save Changes'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
