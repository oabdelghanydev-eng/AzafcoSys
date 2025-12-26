'use client';

import { User, FileText } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';
import { useCustomers } from '@/hooks/api/use-customers';
import Link from 'next/link';
import type { Customer } from '@/types/api';

export default function CustomerStatementSelectPage() {
    const { data, isLoading } = useCustomers();
    const customers = data?.data || [];

    return (
        <div className="p-6 space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <div className="p-3 bg-primary/10 rounded-lg">
                    <User className="h-6 w-6 text-primary" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold">كشف حساب العميل</h1>
                    <p className="text-muted-foreground">Customer Statement - Select Customer</p>
                </div>
            </div>

            {/* Info */}
            <Card>
                <CardContent className="pt-6">
                    <p className="text-muted-foreground">
                        اختر العميل من القائمة أدناه لعرض كشف حسابه
                    </p>
                </CardContent>
            </Card>

            {/* Customers List */}
            <Card>
                <CardHeader>
                    <CardTitle>اختر العميل لعرض كشف الحساب</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="text-center py-8 text-muted-foreground">
                            جاري التحميل...
                        </div>
                    ) : customers.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            لا يوجد عملاء
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
                                {customers.map((customer) => (
                                    <TableRow key={customer.id}>
                                        <TableCell className="font-medium">{customer.customer_code}</TableCell>
                                        <TableCell>{customer.name}</TableCell>
                                        <TableCell>{customer.phone || '-'}</TableCell>
                                        <TableCell className={`text-left money ${customer.balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                            {formatCurrency(customer.balance)}
                                        </TableCell>
                                        <TableCell>
                                            <Link href={`/reports/customer/${customer.id}`}>
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
