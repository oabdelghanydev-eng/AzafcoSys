'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { extractData } from '@/lib/helpers';
import { Account } from '@/types'; // تصحيح 2025-12-13
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export default function AccountsPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const [accounts, setAccounts] = useState<Account[]>([]); // تصحيح 2025-12-13
    const [isLoading, setIsLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (user) {
            api.getAccounts().then((data) => {
                // تصحيح 2025-12-13: cast to proper type
                setAccounts(extractData<Account>(data as Account[] | { data?: Account[] }));
                setIsLoading(false);
            }).catch(() => setIsLoading(false));
        }
    }, [user]);

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

    const filteredAccounts = accounts.filter((a) =>
        a.name?.includes(searchTerm)
    );

    const getTypeLabel = (type: string) => {
        return type === 'cashbox' ? 'خزينة' : 'بنك';
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">الحسابات</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث باسم الحساب..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredAccounts.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا يوجد حسابات</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>اسم الحساب</TableHead>
                                    <TableHead>النوع</TableHead>
                                    <TableHead>الرصيد</TableHead>
                                    <TableHead>الحالة</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredAccounts.map((account) => (
                                    <TableRow key={account.id}>
                                        <TableCell className="font-medium">{account.name}</TableCell>
                                        <TableCell>{getTypeLabel(account.type)}</TableCell>
                                        <TableCell>{account.balance?.toLocaleString()} ج.م</TableCell>
                                        <TableCell>
                                            <span className={`px-2 py-1 rounded text-sm ${account.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                                }`}>
                                                {account.is_active ? 'نشط' : 'غير نشط'}
                                            </span>
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
