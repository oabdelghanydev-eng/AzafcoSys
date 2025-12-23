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
import { Badge } from '@/components/ui/badge';
import { PermissionGate } from '@/components/shared/permission-gate';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney } from '@/lib/formatters';
import { useCustomer, useUpdateCustomer } from '@/hooks/api/use-customers';

export default function CustomerDetailPage() {
    const params = useParams();
    const _router = useRouter();
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState({ name: '', phone: '', address: '' });

    const customerId = Number(params.id);
    const { data: customer, isLoading, error, refetch } = useCustomer(customerId);
    const updateCustomer = useUpdateCustomer();

    // Initialize form when data loads
    if (customer && !formData.name && !isEditing) {
        setFormData({
            name: customer.name || '',
            phone: customer.phone || '',
            address: customer.address || '',
        });
    }

    const handleSave = async () => {
        try {
            await updateCustomer.mutateAsync({
                id: customerId,
                data: formData,
            });
            toast.success('Customer updated successfully');
            setIsEditing(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to update customer');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading customer..." />;
    }

    if (error || !customer) {
        return (
            <ErrorState
                title="Failed to load customer"
                message="Customer not found"
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
                        <Link href="/customers">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{customer.name}</h1>
                            <Badge variant={customer.is_active !== false ? 'default' : 'secondary'}>
                                {customer.is_active !== false ? 'Active' : 'Inactive'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">{customer.customer_code}</p>
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button variant="outline" asChild className="touch-target">
                        <Link href={`/reports/customer?id=${customer.id}`}>
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Link>
                    </Button>
                    {!isEditing ? (
                        <PermissionGate permission="customers.update">
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
            <Card className={customer.balance > 0 ? 'border-orange-200 bg-orange-50' : customer.balance < 0 ? 'border-green-200 bg-green-50' : ''}>
                <CardContent className="p-6">
                    <p className="text-sm text-muted-foreground mb-1">Current Balance</p>
                    <p className={`text-3xl font-bold money ${customer.balance > 0 ? 'text-orange-600' : customer.balance < 0 ? 'text-green-600' : ''}`}>
                        {formatMoney(customer.balance || 0)}
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                        {customer.balance > 0 ? 'Customer owes you' : customer.balance < 0 ? 'You owe customer' : 'Settled'}
                    </p>
                </CardContent>
            </Card>

            {/* Details */}
            <Card>
                <CardHeader>
                    <CardTitle>Customer Details</CardTitle>
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
                            <div className="space-y-2">
                                <Label>Address</Label>
                                <Input
                                    value={formData.address}
                                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                                    className="touch-target"
                                />
                            </div>
                            <Button
                                onClick={handleSave}
                                disabled={updateCustomer.isPending}
                                className="w-full touch-target"
                            >
                                {updateCustomer.isPending ? (
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
                                <span className="font-medium">{customer.phone || '-'}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b">
                                <span className="text-muted-foreground">Address</span>
                                <span className="font-medium">{customer.address || '-'}</span>
                            </div>
                            <div className="flex justify-between py-2">
                                <span className="text-muted-foreground">Code</span>
                                <span className="font-medium">{customer.customer_code}</span>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
