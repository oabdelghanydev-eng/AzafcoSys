'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useCollections } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { formatCurrency, formatDate } from '@/lib/format';
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

export default function CollectionsPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: collections, isLoading } = useCollections();
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

    const collectionList = extractData(collections);
    const filteredCollections = collectionList.filter((col) =>
        col.receipt_number?.includes(searchTerm) ||
        col.customer?.name?.includes(searchTerm)
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">التحصيلات</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                        <Button onClick={() => router.push('/collections/new')}>
                            + تحصيل جديد
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث برقم الإيصال أو اسم العميل..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredCollections.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا توجد تحصيلات</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>رقم الإيصال</TableHead>
                                    <TableHead>العميل</TableHead>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead>المبلغ</TableHead>
                                    <TableHead>طريقة الدفع</TableHead>
                                    <TableHead>التوزيع</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredCollections.map((collection: any) => (
                                    <TableRow key={collection.id}>
                                        <TableCell className="font-medium">{collection.receipt_number}</TableCell>
                                        <TableCell>{collection.customer?.name}</TableCell>
                                        <TableCell>{formatDate(collection.date)}</TableCell>
                                        <TableCell className="text-green-600 font-bold">
                                            {formatCurrency(collection.amount)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={collection.payment_method === 'cash' ? 'default' : 'secondary'}>
                                                {collection.payment_method === 'cash' ? 'نقدي' : 'بنك'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {/* تصحيح 2025-12-13: عرض التسميات الصحيحة */}
                                            {collection.distribution_method === 'oldest_first' && 'الأقدم أولاً'}
                                            {collection.distribution_method === 'newest_first' && 'الأحدث أولاً'}
                                            {collection.distribution_method === 'manual' && 'يدوي'}
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
