'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm, useFieldArray } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { useCustomers, useProducts, useCreateInvoice, useStock } from '@/hooks/useApi';
import { formatCurrency, safeMultiply, safeSum, getTodayISO } from '@/lib/format';
import { extractData } from '@/lib/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Badge } from '@/components/ui/badge';

interface InvoiceFormData {
    customer_id: string;
    date: string;
    type: 'sale' | 'return';
    discount: number;
    items: { product_id: string; quantity: number; unit_price: number }[];
}

export default function NewInvoicePage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: customers = [] } = useCustomers();
    const { data: products = [] } = useProducts();
    const { data: stockData = [] } = useStock();
    const createInvoice = useCreateInvoice();

    const [type, setType] = useState<'sale' | 'return'>('sale');

    const {
        register,
        handleSubmit,
        control,
        watch,
        setValue,
        formState: { errors },
    } = useForm<InvoiceFormData>({
        defaultValues: {
            customer_id: '',
            date: getTodayISO(),
            type: 'sale',
            discount: 0,
            items: [{ product_id: '', quantity: 0, unit_price: 0 }],
        },
    });

    const { fields, append, remove } = useFieldArray({
        control,
        name: 'items',
    });

    const watchItems = watch('items');
    const watchDiscount = watch('discount') || 0;

    // Auth check
    if (authLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-xl">جاري التحميل...</div>
            </div>
        );
    }

    if (!user) {
        router.push('/login');
        return null;
    }

    // Calculate totals
    const subtotal = watchItems.reduce(
        (sum, item) => safeSum(sum, safeMultiply(item.quantity || 0, item.unit_price || 0)),
        0
    );
    const total = safeSum(subtotal, -watchDiscount);

    // Get available stock for a product
    const getAvailableStock = (productId: string) => {
        if (!productId) return 0;
        const stock = stockData.find((s: any) => String(s.product_id) === productId);
        return stock?.available || 0;
    };

    // Customer options
    const customerList = extractData(customers);
    const customerOptions = customerList.map((c) => ({
        value: String(c.id),
        label: `${c.name} (${formatCurrency(c.balance || 0)})`,
    }));

    // Product options
    const productList = extractData(products);
    const productOptions = productList.map((p) => ({
        value: String(p.id),
        label: p.name,
    }));

    const onSubmit = async (data: InvoiceFormData) => {
        try {
            await createInvoice.mutateAsync({
                customer_id: parseInt(data.customer_id),
                date: data.date,
                discount: data.discount,
                items: data.items.map((item) => ({
                    product_id: parseInt(item.product_id),
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                })),
            });
            router.push('/invoices');
        } catch (error: unknown) {
            const message = error instanceof Error ? error.message : 'حدث خطأ';
            alert(message);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <header className="bg-white shadow-sm">
                <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">فاتورة جديدة</h1>
                    <Button variant="outline" onClick={() => router.push('/invoices')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-4xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    {/* Type Toggle */}
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <div className="flex gap-4 justify-center">
                                <Button
                                    type="button"
                                    variant={type === 'sale' ? 'default' : 'outline'}
                                    className={type === 'sale' ? 'bg-blue-600 hover:bg-blue-700' : ''}
                                    onClick={() => {
                                        setType('sale');
                                        setValue('type', 'sale');
                                    }}
                                >
                                    فاتورة بيع
                                </Button>
                                <Button
                                    type="button"
                                    variant={type === 'return' ? 'default' : 'outline'}
                                    className={type === 'return' ? 'bg-red-600 hover:bg-red-700' : ''}
                                    onClick={() => {
                                        setType('return');
                                        setValue('type', 'return');
                                    }}
                                >
                                    مرتجع
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Customer & Date */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات الفاتورة</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>العميل</Label>
                                <Combobox
                                    options={customerOptions}
                                    value={watch('customer_id')}
                                    onChange={(val) => setValue('customer_id', val)}
                                    placeholder="اختر العميل..."
                                    searchPlaceholder="ابحث عن عميل..."
                                />
                                {errors.customer_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.customer_id.message}</p>
                                )}
                            </div>
                            <div>
                                <Label>التاريخ</Label>
                                <Input type="date" {...register('date')} />
                                {errors.date && (
                                    <p className="text-red-500 text-sm mt-1">{errors.date.message}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Items */}
                    <Card className="mb-6">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>البنود</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => append({ product_id: '', quantity: 0, unit_price: 0 })}
                            >
                                + إضافة بند
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {fields.map((field, index) => {
                                const productId = watchItems[index]?.product_id;
                                const available = getAvailableStock(productId);
                                const qty = watchItems[index]?.quantity || 0;
                                const isOverStock = type === 'sale' && qty > available;

                                return (
                                    <div key={field.id} className="grid grid-cols-12 gap-3 mb-4 items-end">
                                        <div className="col-span-4">
                                            <Label>الصنف</Label>
                                            <Combobox
                                                options={productOptions}
                                                value={watchItems[index]?.product_id || ''}
                                                onChange={(val) => setValue(`items.${index}.product_id`, val)}
                                                placeholder="اختر الصنف..."
                                                searchPlaceholder="ابحث..."
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Label>الكمية</Label>
                                            <Input
                                                type="number"
                                                step="0.001"
                                                {...register(`items.${index}.quantity`, { valueAsNumber: true })}
                                                className={isOverStock ? 'border-red-500' : ''}
                                            />
                                            {type === 'sale' && productId && (
                                                <div className="flex items-center gap-1 mt-1">
                                                    <span className="text-xs text-gray-500">المتاح:</span>
                                                    <Badge variant={isOverStock ? 'destructive' : 'secondary'} className="text-xs">
                                                        {available} كجم
                                                    </Badge>
                                                </div>
                                            )}
                                        </div>
                                        <div className="col-span-2">
                                            <Label>السعر</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                {...register(`items.${index}.unit_price`, { valueAsNumber: true })}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Label>الإجمالي</Label>
                                            <div className="h-9 px-3 py-2 bg-gray-100 rounded-md text-sm">
                                                {formatCurrency(safeMultiply(qty, watchItems[index]?.unit_price || 0))}
                                            </div>
                                        </div>
                                        <div className="col-span-2">
                                            {fields.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => remove(index)}
                                                >
                                                    حذف
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                            {errors.items && (
                                <p className="text-red-500 text-sm">{errors.items.message}</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Totals */}
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <Label>الإجمالي الفرعي</Label>
                                    <div className="text-xl font-bold">{formatCurrency(subtotal)}</div>
                                </div>
                                <div>
                                    <Label>الخصم</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        {...register('discount', { valueAsNumber: true })}
                                        className="w-32"
                                    />
                                </div>
                                <div>
                                    <Label>الإجمالي النهائي</Label>
                                    <div className={`text-2xl font-bold ${type === 'return' ? 'text-red-600' : 'text-green-600'}`}>
                                        {formatCurrency(total)}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/invoices')}>
                            إلغاء
                        </Button>
                        <Button
                            type="submit"
                            disabled={createInvoice.isPending}
                            className={type === 'return' ? 'bg-red-600 hover:bg-red-700' : ''}
                        >
                            {createInvoice.isPending ? 'جاري الحفظ...' : 'حفظ الفاتورة'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
