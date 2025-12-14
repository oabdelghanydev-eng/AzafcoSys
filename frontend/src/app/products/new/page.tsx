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

interface ProductFormData {
    name: string;
    name_en: string;
    category: string;
}

export default function NewProductPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const queryClient = useQueryClient();

    const createProduct = useMutation({
        mutationFn: (data: { name: string; name_en?: string; category?: string }) =>
            api.createProduct(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['products'] });
            router.push('/products');
        },
    });

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ProductFormData>({
        defaultValues: {
            name: '',
            name_en: '',
            category: '',
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

    const onSubmit = async (data: ProductFormData) => {
        try {
            await createProduct.mutateAsync({
                name: data.name,
                name_en: data.name_en || undefined,
                category: data.category || undefined,
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
                    <h1 className="text-2xl font-bold text-gray-900">إضافة منتج جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/products')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات المنتج</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label>اسم المنتج *</Label>
                                <Input
                                    {...register('name', { required: 'اسم المنتج مطلوب' })}
                                    placeholder="أدخل اسم المنتج"
                                />
                                {errors.name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.name.message}</p>
                                )}
                            </div>
                            <div>
                                <Label>الاسم بالإنجليزية</Label>
                                <Input
                                    {...register('name_en')}
                                    placeholder="Enter product name"
                                    dir="ltr"
                                />
                            </div>
                            <div>
                                <Label>التصنيف</Label>
                                <Input
                                    {...register('category')}
                                    placeholder="مثال: فواكه، خضروات"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/products')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createProduct.isPending}>
                            {createProduct.isPending ? 'جاري الحفظ...' : 'حفظ المنتج'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
