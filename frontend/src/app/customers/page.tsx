'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useCustomers } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { formatCurrency } from '@/lib/format';
import { extractData } from '@/lib/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export default function CustomersPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: customers, isLoading } = useCustomers();
    const [searchTerm, setSearchTerm] = useState('');

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
    const filteredCustomers = customerList.filter((c) =>
        c.name?.includes(searchTerm) || c.phone?.includes(searchTerm)
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">العملاء</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                        <Button onClick={() => router.push('/customers/new')}>
                            + إضافة عميل
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث بالاسم أو رقم الهاتف..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredCustomers.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا يوجد عملاء</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>الاسم</TableHead>
                                    <TableHead>الهاتف</TableHead>
                                    <TableHead>العنوان</TableHead>
                                    <TableHead>الرصيد</TableHead>
                                    <TableHead>الحالة</TableHead>
                                    <TableHead>الإجراءات</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredCustomers.map((customer: any) => (
                                    <TableRow key={customer.id}>
                                        <TableCell className="font-medium">{customer.name}</TableCell>
                                        <TableCell>{customer.phone || '-'}</TableCell>
                                        <TableCell>{customer.address || '-'}</TableCell>
                                        <TableCell>
                                            <span
                                                className={`font-bold ${customer.balance > 0
                                                    ? 'text-red-600'
                                                    : customer.balance < 0
                                                        ? 'text-green-600'
                                                        : 'text-gray-600'
                                                    }`}
                                            >
                                                {formatCurrency(Math.abs(customer.balance || 0))}
                                            </span>
                                            {customer.balance !== 0 && (
                                                <span className="text-xs text-gray-500 mr-1">
                                                    ({customer.balance > 0 ? 'مديون' : 'دائن'})
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={customer.is_active ? 'default' : 'secondary'}>
                                                {customer.is_active ? 'نشط' : 'غير نشط'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.push(`/customers/${customer.id}`)}
                                            >
                                                كشف حساب
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            </main>
        </div>
    );
}
