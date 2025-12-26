'use client';

import { Truck, FileText } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';
import { useSuppliers } from '@/hooks/api/use-suppliers';
import Link from 'next/link';
import type { Supplier } from '@/types/api';

export default function SupplierStatementSelectPage() {
    const { data, isLoading } = useSuppliers();
    const suppliers = data?.data || [];

    return (
        <div className="p-6 space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <div className="p-3 bg-primary/10 rounded-lg">
                    <Truck className="h-6 w-6 text-primary" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold">كشف حساب المورد</h1>
                    <p className="text-muted-foreground">Supplier Statement - Select Supplier</p>
                </div>
            </div>

            {/* Info */}
            <Card>
                <CardContent className="pt-6">
                    <p className="text-muted-foreground">
                        اختر المورد من القائمة أدناه لعرض كشف حسابه
                    </p>
                </CardContent>
            </Card>

            {/* Suppliers List */}
            <Card>
                <CardHeader>
                    <CardTitle>اختر المورد لعرض كشف الحساب</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="text-center py-8 text-muted-foreground">
                            جاري التحميل...
                        </div>
                    ) : suppliers.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            لا يوجد موردين
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>الكود</TableHead>
                                    <TableHead>الاسم</TableHead>
                                    <TableHead>الهاتف</TableHead>
                                    <TableHead className="text-left">الرصيد</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {suppliers.map((supplier: Supplier) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell className="font-medium">{supplier.supplier_code}</TableCell>
                                        <TableCell>{supplier.name}</TableCell>
                                        <TableCell>{supplier.phone || '-'}</TableCell>
                                        <TableCell className={`text-left money ${supplier.balance < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                            {formatCurrency(Math.abs(supplier.balance))}
                                        </TableCell>
                                        <TableCell>
                                            <Link href={`/reports/supplier/${supplier.id}`}>
                                                <Button size="sm" variant="outline">
                                                    <FileText className="h-4 w-4 ml-2" />
                                                    كشف الحساب
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
