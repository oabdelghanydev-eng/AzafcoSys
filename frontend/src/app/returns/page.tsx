'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { extractData } from '@/lib/helpers';
import { Return } from '@/types'; // تصحيح 2025-12-13
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

export default function ReturnsPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const [returns, setReturns] = useState<Return[]>([]); // تصحيح 2025-12-13
    const [isLoading, setIsLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (user) {
            api.getReturns().then((data) => {
                // تصحيح 2025-12-13: cast to proper type
                setReturns(extractData<Return>(data as Return[] | { data?: Return[] }));
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

    const filteredReturns = returns.filter((r) =>
        r.return_number?.includes(searchTerm)
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">المرتجعات</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                        <Button onClick={() => router.push('/returns/new')}>
                            + إضافة مرتجع
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث برقم المرتجع..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredReturns.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا يوجد مرتجعات</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>رقم المرتجع</TableHead>
                                    <TableHead>العميل</TableHead>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead>الإجمالي</TableHead>
                                    <TableHead>الحالة</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredReturns.map((ret) => (
                                    <TableRow key={ret.id}>
                                        <TableCell className="font-medium">{ret.return_number}</TableCell>
                                        <TableCell>{ret.customer?.name || '-'}</TableCell>
                                        <TableCell>{ret.date}</TableCell>
                                        <TableCell>{ret.total_amount?.toLocaleString()} ج.م</TableCell>
                                        <TableCell>
                                            <span className={`px-2 py-1 rounded text-sm ${ret.status === 'active' ? 'bg-green-100 text-green-800' :
                                                ret.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                                    'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                {ret.status === 'active' ? 'نشط' :
                                                    ret.status === 'cancelled' ? 'ملغي' : 'معلق'}
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
