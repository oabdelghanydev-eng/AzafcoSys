'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { useCustomers, useInvoices, useCreateCollection } from '@/hooks/useApi';
import { formatCurrency, getTodayISO } from '@/lib/format';
import { extractData } from '@/lib/helpers';
import { getErrorMessage } from '@/lib/errors'; // تحسين 2025-12-13
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Badge } from '@/components/ui/badge';

interface CollectionFormData {
    customer_id: string;
    date: string;
    amount: number;
    payment_method: 'cash' | 'bank';
    // تصحيح 2025-12-13: استخدام الأسماء الصحيحة
    distribution_method: 'oldest_first' | 'newest_first' | 'manual';
}

export default function NewCollectionPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: customers = [] } = useCustomers();
    const createCollection = useCreateCollection();

    // تصحيح 2025-12-13: تغيير الافتراضي إلى oldest_first
    const [distributionMethod, setDistributionMethod] = useState<'oldest_first' | 'newest_first' | 'manual'>('oldest_first');
    const [selectedCustomerId, setSelectedCustomerId] = useState('');

    // Get unpaid invoices for selected customer
    const { data: invoices = [] } = useInvoices(
        selectedCustomerId ? { customer_id: selectedCustomerId, status: 'active' } : undefined
    );

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        formState: { errors },
    } = useForm<CollectionFormData>({
        defaultValues: {
            customer_id: '',
            date: getTodayISO(),
            amount: 0,
            payment_method: 'cash',
            distribution_method: 'oldest_first', // تصحيح 2025-12-13
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
    const customerOptions = customerList.map((c) => ({
        value: String(c.id),
        label: `${c.name} (${formatCurrency(c.balance || 0)})`,
    }));

    const invoiceList = extractData(invoices);
    const unpaidInvoices = invoiceList.filter(
        (inv) => inv.status === 'active' && inv.balance > 0
    );

    const onSubmit = async (data: CollectionFormData) => {
        try {
            await createCollection.mutateAsync({
                customer_id: parseInt(data.customer_id),
                date: data.date,
                amount: data.amount,
                payment_method: data.payment_method,
                distribution_method: data.distribution_method,
            });
            router.push('/collections');
        } catch (error: unknown) {
            // تحسين 2025-12-13: استخدام getErrorMessage للحصول على رسالة الخطأ بالعربية
            alert(getErrorMessage(error, 'ar'));
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">تحصيل جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/collections')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-4xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    {/* Customer & Date */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات التحصيل</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>العميل</Label>
                                <Combobox
                                    options={customerOptions}
                                    value={watch('customer_id')}
                                    onChange={(val) => {
                                        setValue('customer_id', val);
                                        setSelectedCustomerId(val);
                                    }}
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
                            </div>
                        </CardContent>
                    </Card>

                    {/* Amount & Payment Method */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>المبلغ وطريقة الدفع</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>المبلغ</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    {...register('amount', { valueAsNumber: true })}
                                    className="text-2xl font-bold"
                                />
                                {errors.amount && (
                                    <p className="text-red-500 text-sm mt-1">{errors.amount.message}</p>
                                )}
                            </div>
                            <div>
                                <Label>طريقة الدفع</Label>
                                <div className="flex gap-4 mt-2">
                                    <Button
                                        type="button"
                                        variant={watch('payment_method') === 'cash' ? 'default' : 'outline'}
                                        onClick={() => setValue('payment_method', 'cash')}
                                    >
                                        نقدي
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={watch('payment_method') === 'bank' ? 'default' : 'outline'}
                                        onClick={() => setValue('payment_method', 'bank')}
                                    >
                                        بنك
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Distribution Method */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>طريقة التوزيع</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-4 mb-4">
                                <Button
                                    type="button"
                                    variant={distributionMethod === 'oldest_first' ? 'default' : 'outline'}
                                    onClick={() => {
                                        setDistributionMethod('oldest_first');
                                        setValue('distribution_method', 'oldest_first');
                                    }}
                                >
                                    الأقدم أولاً (FIFO)
                                </Button>
                                <Button
                                    type="button"
                                    variant={distributionMethod === 'newest_first' ? 'default' : 'outline'}
                                    onClick={() => {
                                        setDistributionMethod('newest_first');
                                        setValue('distribution_method', 'newest_first');
                                    }}
                                >
                                    الأحدث أولاً (LIFO)
                                </Button>
                                <Button
                                    type="button"
                                    variant={distributionMethod === 'manual' ? 'default' : 'outline'}
                                    onClick={() => {
                                        setDistributionMethod('manual');
                                        setValue('distribution_method', 'manual');
                                    }}
                                >
                                    يدوي
                                </Button>
                            </div>

                            {/* Show unpaid invoices if manual */}
                            {distributionMethod === 'manual' && selectedCustomerId && (
                                <div className="mt-4">
                                    <Label className="mb-2 block">الفواتير غير المسددة:</Label>
                                    {unpaidInvoices.length === 0 ? (
                                        <p className="text-gray-500">لا توجد فواتير غير مسددة لهذا العميل</p>
                                    ) : (
                                        <div className="space-y-2 max-h-60 overflow-y-auto">
                                            {unpaidInvoices.map((inv: any) => (
                                                <div
                                                    key={inv.id}
                                                    className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                                >
                                                    <div>
                                                        <span className="font-medium">{inv.invoice_number}</span>
                                                        <span className="text-gray-500 mr-2">({inv.date})</span>
                                                    </div>
                                                    <Badge variant="secondary">
                                                        متبقي: {formatCurrency(inv.balance)}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/collections')}>
                            إلغاء
                        </Button>
                        <Button
                            type="submit"
                            disabled={createCollection.isPending}
                            className="bg-green-600 hover:bg-green-700"
                        >
                            {createCollection.isPending ? 'جاري الحفظ...' : 'حفظ التحصيل'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
