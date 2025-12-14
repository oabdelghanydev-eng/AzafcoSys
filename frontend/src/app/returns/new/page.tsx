'use client';

// Phase 1: single item only

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { useCustomers, useProducts } from '@/hooks/useApi';
import { api } from '@/lib/api';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { extractData } from '@/lib/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';

interface ReturnFormData {
    customer_id: string;
    product_id: string;
    quantity: number;
    unit_price: number;
    notes: string;
}

export default function NewReturnPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const queryClient = useQueryClient();
    const { data: customers = [] } = useCustomers();
    const { data: products = [] } = useProducts();

    const createReturn = useMutation({
        mutationFn: (data: {
            customer_id: number;
            items: { product_id: number; quantity: number; unit_price: number }[];
            notes?: string;
        }) => api.createReturn(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['returns'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            router.push('/returns');
        },
    });

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        formState: { errors },
    } = useForm<ReturnFormData>({
        defaultValues: {
            customer_id: '',
            product_id: '',
            quantity: 1,
            unit_price: 0,
            notes: '',
        },
    });

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

    const customerList = extractData(customers);
    const customerOptions = customerList.map((c: { id: number; name: string }) => ({
        value: String(c.id),
        label: c.name,
    }));

    const productList = extractData(products);
    const productOptions = productList.map((p: { id: number; name: string }) => ({
        value: String(p.id),
        label: p.name,
    }));

    const onSubmit = async (data: ReturnFormData) => {
        try {
            // Phase 1: single item only
            await createReturn.mutateAsync({
                customer_id: parseInt(data.customer_id),
                items: [
                    {
                        product_id: parseInt(data.product_id),
                        quantity: data.quantity,
                        unit_price: data.unit_price,
                    },
                ],
                notes: data.notes || undefined,
            });
        } catch (error: unknown) {
            const message = error instanceof Error ? error.message : 'حدث خطأ';
            alert(message);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">إضافة مرتجع جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/returns')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات المرتجع</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label>العميل *</Label>
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
                        </CardContent>
                    </Card>

                    {/* Phase 1: single item only */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>الصنف المرتجع</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label>المنتج *</Label>
                                <Combobox
                                    options={productOptions}
                                    value={watch('product_id')}
                                    onChange={(val) => setValue('product_id', val)}
                                    placeholder="اختر المنتج..."
                                    searchPlaceholder="ابحث عن منتج..."
                                />
                                {errors.product_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.product_id.message}</p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>الكمية *</Label>
                                    <Input
                                        type="number"
                                        step="0.001"
                                        {...register('quantity', { valueAsNumber: true, required: 'الكمية مطلوبة' })}
                                    />
                                    {errors.quantity && (
                                        <p className="text-red-500 text-sm mt-1">{errors.quantity.message}</p>
                                    )}
                                </div>
                                <div>
                                    <Label>السعر *</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        {...register('unit_price', { valueAsNumber: true, required: 'السعر مطلوب' })}
                                    />
                                    {errors.unit_price && (
                                        <p className="text-red-500 text-sm mt-1">{errors.unit_price.message}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>ملاحظات</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                {...register('notes')}
                                placeholder="أي ملاحظات إضافية..."
                                rows={3}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/returns')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createReturn.isPending}>
                            {createReturn.isPending ? 'جاري الحفظ...' : 'حفظ المرتجع'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
