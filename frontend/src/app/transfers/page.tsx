'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { extractData } from '@/lib/helpers';
import { Account } from '@/types'; // تصحيح 2025-12-13
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface TransferFormData {
    from_account_id: string;
    to_account_id: string;
    amount: number;
    notes: string;
}

export default function TransfersPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const queryClient = useQueryClient();
    const [accounts, setAccounts] = useState<Account[]>([]); // تصحيح 2025-12-13
    const [isLoadingAccounts, setIsLoadingAccounts] = useState(true);

    useEffect(() => {
        if (user) {
            api.getAccounts().then((data) => {
                // تصحيح 2025-12-13: cast to proper type
                setAccounts(extractData<Account>(data as Account[] | { data?: Account[] }));
                setIsLoadingAccounts(false);
            }).catch(() => setIsLoadingAccounts(false));
        }
    }, [user]);

    const createTransfer = useMutation({
        mutationFn: (data: {
            from_account_id: number;
            to_account_id: number;
            amount: number;
            date: string;
            notes?: string;
        }) => api.createTransfer(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            router.push('/accounts');
        },
    });

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        formState: { errors },
        setError,
    } = useForm<TransferFormData>({
        defaultValues: {
            from_account_id: '',
            to_account_id: '',
            amount: 0,
            notes: '',
        },
    });

    if (authLoading || isLoadingAccounts) {
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

    const getAccountLabel = (account: { name: string; type: string }) => {
        const typeLabel = account.type === 'cashbox' ? 'خزينة' : 'بنك';
        return `${account.name} (${typeLabel})`;
    };

    const onSubmit = async (data: TransferFormData) => {
        // Validation: from ≠ to
        if (data.from_account_id === data.to_account_id) {
            setError('to_account_id', { message: 'لا يمكن التحويل لنفس الحساب' });
            return;
        }

        // Validation: amount > 0
        if (data.amount <= 0) {
            setError('amount', { message: 'المبلغ يجب أن يكون أكبر من صفر' });
            return;
        }

        try {
            // Business Date (MVP): system date. To be replaced by Business Day logic in Phase 2.
            const businessDate = new Date().toISOString().split('T')[0];

            await createTransfer.mutateAsync({
                from_account_id: parseInt(data.from_account_id),
                to_account_id: parseInt(data.to_account_id),
                amount: data.amount,
                date: businessDate,
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
                    <h1 className="text-2xl font-bold text-gray-900">تحويل جديد</h1>
                    <Button variant="outline" onClick={() => router.push('/accounts')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات التحويل</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label>من حساب *</Label>
                                <Select
                                    value={watch('from_account_id')}
                                    onValueChange={(val) => setValue('from_account_id', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="اختر الحساب المصدر..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={String(account.id)}>
                                                {getAccountLabel(account)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.from_account_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.from_account_id.message}</p>
                                )}
                            </div>

                            <div>
                                <Label>إلى حساب *</Label>
                                <Select
                                    value={watch('to_account_id')}
                                    onValueChange={(val) => setValue('to_account_id', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="اختر الحساب المستلم..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={String(account.id)}>
                                                {getAccountLabel(account)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.to_account_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.to_account_id.message}</p>
                                )}
                            </div>

                            <div>
                                <Label>المبلغ *</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    {...register('amount', { valueAsNumber: true, required: 'المبلغ مطلوب' })}
                                    placeholder="0.00"
                                />
                                {errors.amount && (
                                    <p className="text-red-500 text-sm mt-1">{errors.amount.message}</p>
                                )}
                            </div>

                            <div>
                                <Label>ملاحظات</Label>
                                <Textarea
                                    {...register('notes')}
                                    placeholder="أي ملاحظات إضافية..."
                                    rows={3}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/accounts')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createTransfer.isPending}>
                            {createTransfer.isPending ? 'جاري التحويل...' : 'تنفيذ التحويل'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
