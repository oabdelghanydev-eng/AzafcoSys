'use client';

import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface CustomerFormData {
    name: string;
    phone: string;
    address: string;
}

export default function NewCustomerPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const queryClient = useQueryClient();

    const createCustomer = useMutation({
        mutationFn: (data: { name: string; phone?: string; address?: string }) =>
            api.createCustomer(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            router.push('/customers');
        },
    });

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<CustomerFormData>({
        defaultValues: {
            name: '',
            phone: '',
            address: '',
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

    const onSubmit = async (data: CustomerFormData) => {
        try {
            await createCustomer.mutateAsync({
                name: data.name,
                phone: data.phone || undefined,
                address: data.address || undefined,
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
                    <h1 className="text-2xl font-bold text-gray-900">إضافة عميل جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/customers')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات العميل</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label>اسم العميل *</Label>
                                <Input
                                    {...register('name', { required: 'اسم العميل مطلوب' })}
                                    placeholder="أدخل اسم العميل"
                                />
                                {errors.name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.name.message}</p>
                                )}
                            </div>
                            <div>
                                <Label>رقم الهاتف</Label>
                                <Input
                                    {...register('phone')}
                                    placeholder="01xxxxxxxxx"
                                    dir="ltr"
                                />
                            </div>
                            <div>
                                <Label>العنوان</Label>
                                <Input
                                    {...register('address')}
                                    placeholder="أدخل العنوان"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/customers')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createCustomer.isPending}>
                            {createCustomer.isPending ? 'جاري الحفظ...' : 'حفظ العميل'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
