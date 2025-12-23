'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Edit2, FileText, Save, X, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney } from '@/lib/formatters';
import { useSupplier, useUpdateSupplier } from '@/hooks/api/use-suppliers';

export default function SupplierDetailPage() {
    const params = useParams();
    const _router = useRouter();
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState({ name: '', phone: '' });

    const supplierId = Number(params.id);
    const { data: supplier, isLoading, error, refetch } = useSupplier(supplierId);
    const updateSupplier = useUpdateSupplier();

    // Initialize form when data loads
    if (supplier && !formData.name && !isEditing) {
        setFormData({
            name: supplier.name || '',
            phone: supplier.phone || '',
        });
    }

    const handleSave = async () => {
        try {
            await updateSupplier.mutateAsync({
                id: supplierId,
                data: formData,
            });
            toast.success('Supplier updated successfully');
            setIsEditing(false);
            refetch();
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
        <div className="space-y-6 max-w-2xl">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/suppliers">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">{supplier.name}</h1>
                        <p className="text-muted-foreground">{supplier.supplier_code}</p>
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button variant="outline" asChild className="touch-target">
                        <Link href={`/reports/supplier?id=${supplier.id}`}>
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Link>
                    </Button>
                    {!isEditing ? (
                        <PermissionGate permission="suppliers.update">
                            <Button onClick={() => setIsEditing(true)} className="touch-target">
                                <Edit2 className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGate>
                    ) : (
                        <Button variant="ghost" onClick={() => setIsEditing(false)} className="touch-target">
                            <X className="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                    )}
                </div>
            </div>

            {/* Balance Card */}
            <Card className={supplier.balance > 0 ? 'border-red-200 bg-red-50' : ''}>
                <CardContent className="p-6">
                    <p className="text-sm text-muted-foreground mb-1">Current Balance</p>
                    <p className={`text-3xl font-bold money ${supplier.balance > 0 ? 'text-red-600' : ''}`}>
                        {formatMoney(supplier.balance || 0)}
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                        {supplier.balance > 0 ? 'You owe supplier' : 'Settled'}
                    </p>
                </CardContent>
            </Card>

            {/* Details */}
            <Card>
                <CardHeader>
                    <CardTitle>Supplier Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {isEditing ? (
                        <>
                            <div className="space-y-2">
                                <Label>Name</Label>
                                <Input
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    className="touch-target"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Phone</Label>
                                <Input
                                    type="tel"
                                    value={formData.phone}
                                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                    className="touch-target"
                                />
                            </div>
                            <Button
                                onClick={handleSave}
                                disabled={updateSupplier.isPending}
                                className="w-full touch-target"
                            >
                                {updateSupplier.isPending ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="mr-2 h-4 w-4" />
                                )}
                                Save Changes
                            </Button>
                        </>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex justify-between py-2 border-b">
                                <span className="text-muted-foreground">Phone</span>
                                <span className="font-medium">{supplier.phone || '-'}</span>
                            </div>
                            <div className="flex justify-between py-2">
                                <span className="text-muted-foreground">Code</span>
                                <span className="font-medium">{supplier.supplier_code}</span>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
