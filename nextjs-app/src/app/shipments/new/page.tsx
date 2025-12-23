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
import { formatQuantity } from '@/lib/formatters';
import { useSuppliers } from '@/hooks/api/use-suppliers';
import { useProducts } from '@/hooks/api/use-products';
import { useCreateShipment } from '@/hooks/api/use-shipments';

interface ShipmentItem {
    product_id: number;
    product_name: string;
    cartons: number;
    weight_per_unit: number;
}

export default function NewShipmentPage() {
    const router = useRouter();
    const [supplierId, setSupplierId] = useState('');
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [items, setItems] = useState<ShipmentItem[]>([]);

    // Form for adding item
    const [selectedProduct, setSelectedProduct] = useState('');
    const [cartons, setCartons] = useState('');
    const [weightPerUnit, setWeightPerUnit] = useState('');

    // API hooks
    const { data: suppliersData, isLoading: suppliersLoading } = useSuppliers();
    const { data: productsData, isLoading: productsLoading } = useProducts();
    const createShipment = useCreateShipment();

    const suppliers = suppliersData?.data ?? suppliersData ?? [];
    const productsRaw = productsData?.data ?? productsData ?? [];
    // Sort products by ID and ensure it's an array
    const products = Array.isArray(productsRaw)
        ? [...productsRaw].sort((a: { id: number }, b: { id: number }) => a.id - b.id)
        : [];

    // Auto-select first supplier for faster data entry
    if (!supplierId && Array.isArray(suppliers) && suppliers.length > 0) {
        setSupplierId(suppliers[0].id.toString());
    }

    const handleAddItem = () => {
        if (!selectedProduct || !cartons || !weightPerUnit) {
            toast.error('Please fill all item fields');
            return;
        }

        const product = Array.isArray(products)
            ? products.find((p: { id: number }) => p.id.toString() === selectedProduct)
            : null;
        if (!product) return;

        const newItem: ShipmentItem = {
            product_id: product.id,
            product_name: product.name_en || product.name,
            cartons: parseInt(cartons),
            weight_per_unit: parseFloat(weightPerUnit),
        };

        setItems([...items, newItem]);
        setSelectedProduct('');
        setCartons('');
        setWeightPerUnit('');
        toast.success(`${product.name_en || product.name} added`);
    };

    const handleRemoveItem = (index: number) => {
        setItems(items.filter((_, i) => i !== index));
    };

    const handleSubmit = async () => {
        if (!supplierId || !date || items.length === 0) {
            toast.error('Please fill all required fields');
            return;
        }

        try {
            await createShipment.mutateAsync({
                supplier_id: parseInt(supplierId),
                date,
                items: items.map(item => ({
                    product_id: item.product_id,
                    cartons: item.cartons,
                    weight_per_unit: item.weight_per_unit,
                })),
            });
            toast.success('Shipment created successfully');
            router.push('/shipments');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to create shipment');
        }
    };

    const totalCartons = items.reduce((sum, item) => sum + item.cartons, 0);

    if (suppliersLoading || productsLoading) {
        return <LoadingState message="Loading..." />;
    }

    return (
        <div className="space-y-6 pb-24">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" asChild>
                    <Link href="/shipments">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-2xl font-bold">New Shipment</h1>
                    <p className="text-muted-foreground">Record incoming inventory</p>
                </div>
            </div>

            {/* Form */}
            <Card>
                <CardHeader>
                    <CardTitle>Shipment Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
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
                        <div className="space-y-2">
                            <Label>Date *</Label>
                            <Input
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                                className="touch-target"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Add Item */}
            <Card>
                <CardHeader>
                    <CardTitle>Add Item</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-4">
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
                            <Label className="text-xs">Weight/unit</Label>
                            <Input
                                type="number"
                                inputMode="decimal"
                                value={weightPerUnit}
                                onChange={(e) => setWeightPerUnit(e.target.value)}
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
                                        {item.cartons} cartons × {formatQuantity(item.weight_per_unit)}kg
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-destructive"
                                    onClick={() => handleRemoveItem(index)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {/* Sticky Bottom */}
            <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Total Cartons</p>
                        <p className="text-2xl font-bold">{totalCartons}</p>
                    </div>
                    <Button
                        size="lg"
                        onClick={handleSubmit}
                        disabled={createShipment.isPending || !supplierId || items.length === 0}
                        className="touch-target px-8"
                    >
                        {createShipment.isPending ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Create Shipment'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
