'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useShipments } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { formatDate } from '@/lib/format';
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

export default function ShipmentsPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: shipments, isLoading } = useShipments();
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

    const shipmentList = extractData(shipments);
    const filteredShipments = shipmentList.filter((s) =>
        s.number?.includes(searchTerm) || s.supplier?.name?.includes(searchTerm)
    );

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'open':
                return <Badge className="bg-green-100 text-green-800">مفتوحة</Badge>;
            case 'closed':
                return <Badge className="bg-yellow-100 text-yellow-800">مغلقة</Badge>;
            case 'settled':
                return <Badge className="bg-gray-100 text-gray-800">مُصفاة</Badge>;
            default:
                return <Badge>{status}</Badge>;
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">الشحنات</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/')}>
                            الرئيسية
                        </Button>
                        <Button onClick={() => router.push('/shipments/new')}>
                            + شحنة جديدة
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                <div className="mb-6">
                    <Input
                        placeholder="بحث برقم الشحنة أو اسم المورد..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredShipments.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا توجد شحنات</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>رقم الشحنة</TableHead>
                                    <TableHead>المورد</TableHead>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead>عدد الأصناف</TableHead>
                                    <TableHead>الحالة</TableHead>
                                    <TableHead>الإجراءات</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredShipments.map((shipment: any) => (
                                    <TableRow key={shipment.id}>
                                        <TableCell className="font-medium">{shipment.number}</TableCell>
                                        <TableCell>{shipment.supplier?.name}</TableCell>
                                        <TableCell>{formatDate(shipment.date)}</TableCell>
                                        <TableCell>{shipment.items?.length || 0} صنف</TableCell>
                                        <TableCell>{getStatusBadge(shipment.status)}</TableCell>
                                        <TableCell>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.push(`/shipments/${shipment.id}`)}
                                            >
                                                عرض
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
