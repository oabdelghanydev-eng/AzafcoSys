'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Plus, Trash2, Loader2 } from 'lucide-react';
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
import { formatMoney, formatQuantity } from '@/lib/formatters';
import { useCustomers } from '@/hooks/api/use-customers';
import { useProducts } from '@/hooks/api/use-products';
import { useCreateReturn } from '@/hooks/api/use-returns';

interface ReturnItem {
    product_id: number;
    product_name: string;
    cartons: number;
    weight: number;
    price: number;
}

export default function NewReturnPage() {
    const router = useRouter();
    const [customerId, setCustomerId] = useState('');
    const [items, setItems] = useState<ReturnItem[]>([]);

    // Form for adding item
    const [selectedProduct, setSelectedProduct] = useState('');
    const [cartons, setCartons] = useState('');
    const [weight, setWeight] = useState('');
    const [price, setPrice] = useState('');

    // API hooks
    const { data: customersData, isLoading: customersLoading } = useCustomers();
    const { data: productsData, isLoading: productsLoading } = useProducts();
    const createReturn = useCreateReturn();

    const customers = customersData?.data ?? customersData ?? [];
    const productsRaw = productsData?.data ?? productsData ?? [];
    // Sort products by ID
    const products = Array.isArray(productsRaw)
        ? [...productsRaw].sort((a: { id: number }, b: { id: number }) => a.id - b.id)
        : [];

    const handleAddItem = () => {
        if (!selectedProduct || !cartons || !weight || !price) {
            toast.error('Please fill all item fields');
            return;
        }

        const product = Array.isArray(products)
            ? products.find((p: { id: number }) => p.id.toString() === selectedProduct)
            : null;
        if (!product) return;

        const newItem: ReturnItem = {
            product_id: product.id,
            product_name: product.name_en || product.name,
            cartons: parseInt(cartons),
            weight: parseFloat(weight),
            price: parseFloat(price),
        };

        setItems([...items, newItem]);
        setSelectedProduct('');
        setCartons('');
        setWeight('');
        setPrice('');
        toast.success(`${product.name_en || product.name} added`);
    };

    const handleRemoveItem = (index: number) => {
        setItems(items.filter((_, i) => i !== index));
    };

    const handleSubmit = async () => {
        if (!customerId || items.length === 0) {
            toast.error('Please select a customer and add items');
            return;
        }

        try {
            await createReturn.mutateAsync({
                customer_id: parseInt(customerId),
                date: new Date().toISOString().split('T')[0],
                items: items.map(item => ({
                    product_id: item.product_id,
                    cartons: item.cartons,
                    weight: item.weight,
                    price: item.price,
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

    if (customersLoading || productsLoading) {
        return <LoadingState message="Loading..." />;
    }

    return (
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
                    <p className="text-muted-foreground">Record a customer return</p>
                </div>
            </div>

            {/* Customer */}
            <Card>
                <CardHeader>
                    <CardTitle>Customer</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        <Label>Select Customer *</Label>
                        <Select value={customerId} onValueChange={setCustomerId}>
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

            {/* Add Item */}
            <Card>
                <CardHeader>
                    <CardTitle>Add Item</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1 sm:col-span-2">
                            <Label className="text-xs">Product</Label>
                            <Select value={selectedProduct} onValueChange={setSelectedProduct}>
                                <SelectTrigger className="touch-target">
                                    <SelectValue placeholder="Select product" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((p: { id: number; name_en?: string; name?: string }) => (
                                        <SelectItem key={p.id} value={p.id.toString()}>
                                            {p.name_en || p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Cartons</Label>
                            <Input
                                type="number"
                                inputMode="numeric"
                                value={cartons}
                                onChange={(e) => setCartons(e.target.value)}
                                className="touch-target"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Weight (kg)</Label>
                            <Input
                                type="number"
                                inputMode="decimal"
                                value={weight}
                                onChange={(e) => setWeight(e.target.value)}
                                className="touch-target"
                            />
                        </div>
                        <div className="space-y-1 sm:col-span-2">
                            <Label className="text-xs">Price per KG</Label>
                            <Input
                                type="number"
                                inputMode="decimal"
                                value={price}
                                onChange={(e) => setPrice(e.target.value)}
                                className="touch-target"
                            />
                        </div>
                    </div>
                    <Button onClick={handleAddItem} variant="outline" className="w-full touch-target">
                        <Plus className="mr-2 h-4 w-4" />
                        Add Item
                    </Button>
                </CardContent>
            </Card>

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
                        disabled={createReturn.isPending || !customerId || items.length === 0}
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
    );
}
