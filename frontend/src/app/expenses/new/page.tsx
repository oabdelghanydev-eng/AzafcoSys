'use client';

import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ExpenseFormData {
    type: 'supplier' | 'company';
    supplier_id?: string;
    date: string;
    amount: string;
    description: string;
    payment_method: 'cash' | 'bank';
    category?: string;
    notes?: string;
}

interface Supplier {
    id: number;
    name: string;
}

export default function NewExpensePage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const queryClient = useQueryClient();

    const { data: suppliers } = useQuery({
        queryKey: ['suppliers'],
        queryFn: () => api.getSuppliers(),
        enabled: !!user,
    });

    const createExpense = useMutation({
        mutationFn: (data: {
            type: 'supplier' | 'company';
            supplier_id?: number;
            date: string;
            amount: number;
            description: string;
            payment_method: 'cash' | 'bank';
            category?: string;
            notes?: string;
        }) => api.createExpense(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['expenses'] });
            router.push('/expenses');
        },
    });

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        formState: { errors },
    } = useForm<ExpenseFormData>({
        defaultValues: {
            type: 'company',
            date: new Date().toISOString().split('T')[0],
            amount: '',
            description: '',
            payment_method: 'cash',
        },
    });

    const expenseType = watch('type');

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

    const onSubmit = async (data: ExpenseFormData) => {
        try {
            await createExpense.mutateAsync({
                type: data.type,
                supplier_id: data.supplier_id ? parseInt(data.supplier_id) : undefined,
                date: data.date,
                amount: parseFloat(data.amount),
                description: data.description,
                payment_method: data.payment_method,
                category: data.category || undefined,
                notes: data.notes || undefined,
            });
        } catch (error: unknown) {
            const message = error instanceof Error ? error.message : 'حدث خطأ';
            alert(message);
        }
    };

    const suppliersList = (suppliers as { data: Supplier[] })?.data || suppliers || [];

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">إضافة مصروف جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/expenses')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات المصروف</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>نوع المصروف *</Label>
                                    <Select
                                        value={expenseType}
                                        onValueChange={(value: 'supplier' | 'company') => setValue('type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="company">مصروفات شركة</SelectItem>
                                            <SelectItem value="supplier">مصروفات مورد</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label>التاريخ *</Label>
                                    <Input
                                        type="date"
                                        {...register('date', { required: 'التاريخ مطلوب' })}
                                    />
                                    {errors.date && (
                                        <p className="text-red-500 text-sm mt-1">{errors.date.message}</p>
                                    )}
                                </div>
                            </div>

                            {expenseType === 'supplier' && (
                                <div>
                                    <Label>المورد *</Label>
                                    <Select
                                        onValueChange={(value) => setValue('supplier_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="اختر المورد" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(Array.isArray(suppliersList) ? suppliersList : []).map((supplier: Supplier) => (
                                                <SelectItem key={supplier.id} value={supplier.id.toString()}>
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>المبلغ *</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        {...register('amount', {
                                            required: 'المبلغ مطلوب',
                                            min: { value: 0.01, message: 'المبلغ يجب أن يكون أكبر من صفر' }
                                        })}
                                        placeholder="0.00"
                                        dir="ltr"
                                    />
                                    {errors.amount && (
                                        <p className="text-red-500 text-sm mt-1">{errors.amount.message}</p>
                                    )}
                                </div>
                                <div>
                                    <Label>طريقة الدفع *</Label>
                                    <Select
                                        defaultValue="cash"
                                        onValueChange={(value: 'cash' | 'bank') => setValue('payment_method', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="cash">نقدي</SelectItem>
                                            <SelectItem value="bank">بنك</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div>
                                <Label>الوصف *</Label>
                                <Input
                                    {...register('description', { required: 'الوصف مطلوب' })}
                                    placeholder="أدخل وصف المصروف"
                                />
                                {errors.description && (
                                    <p className="text-red-500 text-sm mt-1">{errors.description.message}</p>
                                )}
                            </div>

                            <div>
                                <Label>التصنيف</Label>
                                <Input
                                    {...register('category')}
                                    placeholder="مثال: نقل، صيانة، رواتب"
                                />
                            </div>

                            <div>
                                <Label>ملاحظات</Label>
                                <Input
                                    {...register('notes')}
                                    placeholder="ملاحظات إضافية"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/expenses')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createExpense.isPending}>
                            {createExpense.isPending ? 'جاري الحفظ...' : 'حفظ المصروف'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
