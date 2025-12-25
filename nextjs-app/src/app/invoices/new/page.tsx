'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, Trash2, ShoppingCart, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { LoadingState } from '@/components/shared/loading-state';
import { RequireOpenDay } from '@/components/shared/require-open-day';
import { formatMoney, formatQuantity } from '@/lib/formatters';
import { useCustomers } from '@/hooks/api/use-customers';
import { useStock } from '@/hooks/api/use-shipments';
import { useCreateInvoice } from '@/hooks/api/use-invoices';
import type { StockItem, Customer } from '@/types/api';

interface CartItem {
    product_id: number;
    product_name: string;
    cartons: number;
    weight: number;
    price: number;
    line_total: number;
}

export default function NewInvoicePage() {
    const router = useRouter();
    const [customerId, setCustomerId] = useState<string>('');
    const [selectedProduct, setSelectedProduct] = useState<StockItem | null>(null);
    const [cartons, setCartons] = useState('');
    const [weight, setWeight] = useState('');
    const [price, setPrice] = useState('');
    const [cart, setCart] = useState<CartItem[]>([]);
    const [discount, setDiscount] = useState('0');

    // API hooks
    const { data: customersData, isLoading: customersLoading } = useCustomers();
    const { data: stockData, isLoading: stockLoading } = useStock();
    const createInvoice = useCreateInvoice();

    const customers = customersData?.data || [];
    const stockRaw = stockData?.data || stockData || [];

    // The API returns grouped stock - flatten it for product selection
    // Each item has: product_id, product_name, total_quantity, items[]
    const availableProducts = Array.isArray(stockRaw)
        ? stockRaw.filter((p: StockItem) => (p.total_quantity || p.remaining_cartons || 0) > 0).map((p: StockItem) => ({
            product_id: p.product_id,
            product_name: p.product_name,
            remaining_cartons: p.total_quantity || p.remaining_cartons || 0,
            // Get weight from first item if available
            weight_per_unit: p.items?.[0]?.weight_per_unit || 20,
            shipment_id: p.items?.[0]?.id || null,
        }))
        : [];

    // Calculate how many cartons of a product are already in the cart
    const getCartQuantityForProduct = (productId: number): number => {
        return cart
            .filter(item => item.product_id === productId)
            .reduce((sum, item) => sum + item.cartons, 0);
    };

    // Calculate remaining available stock after subtracting cart items
    const getRemainingAvailable = (productId: number): number => {
        const product = availableProducts.find((p: { product_id: number }) => p.product_id === productId);
        if (!product) return 0;
        const originalStock = product.remaining_cartons;
        const inCart = getCartQuantityForProduct(productId);
        return Math.max(0, originalStock - inCart);
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const handleProductSelect = (product: any) => {
        const remaining = getRemainingAvailable(product.product_id);
        if (remaining <= 0) {
            toast.error(
                <div className="space-y-1">
                    <p>No available quantity for this product</p>
                    <a
                        href="/shipments"
                        className="text-primary underline text-sm"
                    >
                        Manage Shipments →
                    </a>
                </div>,
                { duration: 5000 }
            );
            return;
        }
        setSelectedProduct({
            ...product,
            // Update remaining_cartons to reflect actual remaining after cart
            remaining_cartons: remaining,
        });
        setWeight(product.weight_per_unit?.toString() || '');
        setCartons('');
        setPrice('');
    };

    const calculateLineTotal = () => {
        const c = parseFloat(cartons) || 0;
        const w = parseFloat(weight) || 0;
        const p = parseFloat(price) || 0;
        return c * w * p;
    };

    const handleAddToCart = () => {
        if (!selectedProduct || !cartons || !weight || !price) {
            toast.error('Please fill all item fields');
            return;
        }

        const c = parseInt(cartons);
        // Get REAL remaining (original stock - cart items)
        const remainingCartons = getRemainingAvailable(selectedProduct.product_id);

        if (c > remainingCartons) {
            toast.error(`Only ${remainingCartons} cartons available`);
            return;
        }

        if (c <= 0) {
            toast.error('Quantity must be greater than zero');
            return;
        }

        const newItem: CartItem = {
            product_id: selectedProduct.product_id,
            product_name: selectedProduct.product_name,
            cartons: c,
            weight: parseFloat(weight),
            price: parseFloat(price),
            line_total: calculateLineTotal(),
        };

        setCart([...cart, newItem]);
        setSelectedProduct(null);
        setCartons('');
        setWeight('');
        setPrice('');
        toast.success(`${newItem.product_name} added to cart`);
    };

    const handleRemoveFromCart = (index: number) => {
        setCart(cart.filter((_, i) => i !== index));
    };

    const cartTotal = cart.reduce((sum, item) => sum + item.line_total, 0);
    const discountAmount = parseFloat(discount) || 0;
    const finalTotal = cartTotal - discountAmount;

    const handleSubmit = async () => {
        if (!customerId) {
            toast.error('Please select a customer');
            return;
        }
        if (cart.length === 0) {
            toast.error('Please add at least one item');
            return;
        }

        try {
            // Use current date (the backend validates against the open daily report)
            const today = new Date().toISOString().split('T')[0];

            await createInvoice.mutateAsync({
                customer_id: parseInt(customerId),
                date: today,
                items: cart.map(item => ({
                    product_id: item.product_id,
                    cartons: item.cartons,
                    total_weight: item.cartons * item.weight,
                    price: item.price,
                })),
                discount: discountAmount,
            });
            toast.success('Invoice created successfully');
            router.push('/invoices');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to create invoice');
        }
    };

    if (customersLoading || stockLoading) {
        return <LoadingState message="Loading..." />;
    }

    return (
        <RequireOpenDay>
            <div className="space-y-6 pb-24">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/invoices">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">New Invoice</h1>
                        <p className="text-muted-foreground">Create a sales invoice</p>
                    </div>
                </div>

                {/* Customer Selection */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Customer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Select value={customerId} onValueChange={setCustomerId}>
                            <SelectTrigger className="touch-target">
                                <SelectValue placeholder="Select customer" />
                            </SelectTrigger>
                            <SelectContent>
                                {customers.map((customer: Customer) => (
                                    <SelectItem key={customer.id} value={customer.id.toString()}>
                                        {customer.name}
                                        {customer.balance > 0 && (
                                            <span className="ml-2 text-orange-600">
                                                (Balance: {formatMoney(customer.balance)})
                                            </span>
                                        )}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                {/* Product Selection - Buttons */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Select Product</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {availableProducts.length === 0 ? (
                            <p className="text-center text-muted-foreground py-4">
                                No products in stock. Create a shipment first.
                            </p>
                        ) : (
                            <>
                                {/* Product Buttons Grid */}
                                <div className="grid grid-cols-3 gap-2">
                                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                                    {availableProducts.map((product: any) => {
                                        const remaining = getRemainingAvailable(product.product_id);
                                        const isOutOfStock = remaining <= 0;
                                        return (
                                            <Button
                                                key={`${product.product_id}-${product.shipment_id}`}
                                                variant={selectedProduct?.product_id === product.product_id ? 'default' : 'outline'}
                                                className={`h-auto py-3 flex-col touch-target ${isOutOfStock ? 'opacity-50' : ''}`}
                                                onClick={() => handleProductSelect(product)}
                                                disabled={isOutOfStock}
                                            >
                                                <span className="font-medium text-sm">{product.product_name}</span>
                                                <span className={`text-xs ${isOutOfStock ? 'text-destructive' : 'opacity-70'}`}>
                                                    {remaining} cart {isOutOfStock && '(sold out)'}
                                                </span>
                                            </Button>
                                        );
                                    })}
                                </div>

                                {/* Item Entry Form */}
                                {selectedProduct && (
                                    <div className="border rounded-lg p-4 space-y-4 bg-muted/30">
                                        <p className="font-medium">Adding: {selectedProduct.product_name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Max: {selectedProduct.remaining_cartons} cartons
                                        </p>

                                        <div className="grid grid-cols-3 gap-3">
                                            <div className="space-y-1">
                                                <Label className="text-xs">Cartons</Label>
                                                <Input
                                                    type="number"
                                                    inputMode="numeric"
                                                    placeholder={`Max ${selectedProduct.remaining_cartons}`}
                                                    value={cartons}
                                                    onChange={(e) => setCartons(e.target.value)}
                                                    className="touch-target"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-xs">Weight/pc</Label>
                                                <Input
                                                    type="number"
                                                    inputMode="decimal"
                                                    value={weight}
                                                    onChange={(e) => setWeight(e.target.value)}
                                                    className="touch-target"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-xs">Price/KG</Label>
                                                <Input
                                                    type="number"
                                                    inputMode="decimal"
                                                    value={price}
                                                    onChange={(e) => setPrice(e.target.value)}
                                                    className="touch-target"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <p className="text-sm">
                                                Line Total: <span className="font-bold">{formatMoney(calculateLineTotal())}</span>
                                            </p>
                                            <Button onClick={handleAddToCart} className="touch-target">
                                                + Add Item
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Cart */}
                {cart.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <ShoppingCart className="h-5 w-5" />
                                Cart ({cart.length} items)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {cart.map((item, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between p-3 rounded-lg bg-muted/50"
                                >
                                    <div>
                                        <p className="font-medium">{item.product_name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.cartons} × {formatQuantity(item.weight)}kg @ {formatMoney(item.price)}/kg
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <p className="font-semibold money">{formatMoney(item.line_total)}</p>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-destructive"
                                            onClick={() => handleRemoveFromCart(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}

                            {/* Discount */}
                            <div className="flex items-center gap-3 pt-3 border-t">
                                <Label className="text-sm">Discount:</Label>
                                <Input
                                    type="number"
                                    inputMode="decimal"
                                    value={discount}
                                    onChange={(e) => setDiscount(e.target.value)}
                                    className="w-32 touch-target"
                                />
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Sticky Bottom */}
                <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t lg:left-[280px]">
                    <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Total</p>
                            <p className="text-2xl font-bold money">{formatMoney(finalTotal)}</p>
                        </div>
                        <Button
                            size="lg"
                            onClick={handleSubmit}
                            disabled={createInvoice.isPending || cart.length === 0 || !customerId}
                            className="touch-target px-8"
                        >
                            {createInvoice.isPending ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                'Save Invoice'
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </RequireOpenDay>
    );
}
