'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useSuppliers } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { extractData } from '@/lib/helpers';
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

export default function SuppliersPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: suppliers, isLoading } = useSuppliers();
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

    const supplierList = extractData(suppliers);
    const filteredSuppliers = supplierList.filter((s) =>
        s.name?.includes(searchTerm)
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">الموردين</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                        <Button onClick={() => router.push('/suppliers/new')}>
                            + إضافة مورد
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث باسم المورد..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredSuppliers.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا يوجد موردين</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>الاسم</TableHead>
                                    <TableHead>الهاتف</TableHead>
                                    <TableHead>العنوان</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredSuppliers.map((supplier: { id: number; name: string; phone?: string; address?: string }) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell className="font-medium">{supplier.name}</TableCell>
                                        <TableCell>{supplier.phone || '-'}</TableCell>
                                        <TableCell>{supplier.address || '-'}</TableCell>
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
