'use client';

import { useRouter } from 'next/navigation';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Expense {
    id: number;
    expense_number: string;
    type: 'supplier' | 'company';
    type_label: string;
    date: string;
    amount: number;
    payment_method: 'cash' | 'bank';
    payment_method_label: string;
    description: string;
    supplier?: {
        id: number;
        name: string;
    };
    shipment?: {
        id: number;
        number: string;
    };
}

export default function ExpensesPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();

    const { data: expensesResponse, isLoading } = useQuery({
        queryKey: ['expenses'],
        queryFn: () => api.getExpenses(),
        enabled: !!user,
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

    const expenses = (expensesResponse as { data: Expense[] })?.data || [];

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">المصروفات</h1>
                    <Button onClick={() => router.push('/expenses/new')}>
                        + إضافة مصروف
                    </Button>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <Card>
                    <CardHeader>
                        <CardTitle>قائمة المصروفات</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="text-center py-8">جاري التحميل...</div>
                        ) : expenses.length === 0 ? (
                            <div className="text-center py-8 text-gray-500">
                                لا توجد مصروفات
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="text-right">الرقم</TableHead>
                                        <TableHead className="text-right">التاريخ</TableHead>
                                        <TableHead className="text-right">النوع</TableHead>
                                        <TableHead className="text-right">الوصف</TableHead>
                                        <TableHead className="text-right">المبلغ</TableHead>
                                        <TableHead className="text-right">طريقة الدفع</TableHead>
                                        <TableHead className="text-right">المورد</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {expenses.map((expense: Expense) => (
                                        <TableRow key={expense.id}>
                                            <TableCell className="font-mono">
                                                {expense.expense_number}
                                            </TableCell>
                                            <TableCell>{expense.date}</TableCell>
                                            <TableCell>
                                                <span className={`px-2 py-1 rounded-full text-sm ${expense.type === 'supplier'
                                                        ? 'bg-blue-100 text-blue-800'
                                                        : 'bg-purple-100 text-purple-800'
                                                    }`}>
                                                    {expense.type_label}
                                                </span>
                                            </TableCell>
                                            <TableCell>{expense.description}</TableCell>
                                            <TableCell className="font-bold">
                                                {expense.amount.toLocaleString('ar-EG')} ج.م
                                            </TableCell>
                                            <TableCell>{expense.payment_method_label}</TableCell>
                                            <TableCell>
                                                {expense.supplier?.name || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </main>
        </div>
    );
}
