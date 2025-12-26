'use client';

import { use } from 'react';
import { useState } from 'react';
import { Download, User, ArrowLeft, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import { formatCurrency } from '@/lib/utils';
import { formatDate } from '@/lib/formatters';
import Link from 'next/link';
import type { ApiResponse } from '@/types/api';

interface StatementTransaction {
    id: number;
    date: string;
    type: 'invoice' | 'collection' | 'opening_balance';
    reference: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
}

interface CustomerStatement {
    customer: {
        id: number;
        code: string;
        name: string;
        phone: string | null;
        opening_balance: number;
    };
    period: {
        from: string | null;
        to: string | null;
    };
    transactions: StatementTransaction[];
    summary: {
        opening_balance: number;
        total_invoices: number;
        total_collections: number;
        closing_balance: number;
    };
}

// Safe number helper to prevent NaN
const safeNumber = (value: number | undefined | null): number => {
    return typeof value === 'number' && !isNaN(value) ? value : 0;
};

export default function CustomerStatementPage({ params }: { params: Promise<{ id: string }> }) {
    const resolvedParams = use(params);
    const customerId = parseInt(resolvedParams.id);

    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const { downloadPdf, isDownloading } = usePdfDownload();

    const buildUrl = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        const queryString = params.toString();
        return `${endpoints.customers.statement(customerId)}${queryString ? `?${queryString}` : ''}`;
    };

    const { data, isLoading, error } = useQuery({
        queryKey: ['customer-statement', customerId, dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<CustomerStatement>>(buildUrl()),
        enabled: !isNaN(customerId),
    });

    const statement = data?.data;

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        const endpoint = `/reports/customer/${customerId}/pdf?${params}`;
        downloadPdf(endpoint, `customer-statement-${customerId}`);
    };

    if (isNaN(customerId)) {
        return (
            <div className="p-6">
                <div className="text-center text-red-500">معرف العميل غير صحيح</div>
            </div>
        );
    }

    return (
        <div className="p-6 space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Link href="/customers">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold">كشف حساب العميل</h1>
                        <p className="text-muted-foreground">Customer Statement</p>
                    </div>
                </div>
                <Button onClick={handleDownloadPdf} disabled={isDownloading || !statement}>
                    {isDownloading ? (
                        <Loader2 className="h-4 w-4 ml-2 animate-spin" />
                    ) : (
                        <Download className="h-4 w-4 ml-2" />
                    )}
                    {isDownloading ? 'جاري التحميل...' : 'تحميل PDF'}
                </Button>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="pt-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <Label>من تاريخ</Label>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label>إلى تاريخ</Label>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {isLoading && (
                <div className="flex justify-center py-12">
                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
            )}

            {error && (
                <Card className="border-red-200 bg-red-50">
                    <CardContent className="pt-6 text-center text-red-600">
                        حدث خطأ في تحميل البيانات
                    </CardContent>
                </Card>
            )}

            {statement && (
                <>
                    {/* Customer Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                {statement.customer.name}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">الكود</p>
                                    <p className="font-medium">{statement.customer.code}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">الهاتف</p>
                                    <p className="font-medium">{statement.customer.phone || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">الرصيد الافتتاحي</p>
                                    <p className="font-medium money">{formatCurrency(statement.summary.opening_balance)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">الرصيد الحالي</p>
                                    <p className={`font-bold text-lg money ${statement.summary.closing_balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                        {formatCurrency(statement.summary.closing_balance)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-sm text-muted-foreground">إجمالي الفواتير</p>
                                <p className="text-2xl font-bold text-red-600 money">
                                    {formatCurrency(statement.summary.total_invoices)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-sm text-muted-foreground">إجمالي التحصيلات</p>
                                <p className="text-2xl font-bold text-green-600 money">
                                    {formatCurrency(statement.summary.total_collections)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-sm text-muted-foreground">الرصيد الختامي</p>
                                <p className={`text-2xl font-bold money ${statement.summary.closing_balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                    {formatCurrency(Math.abs(statement.summary.closing_balance))}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {statement.summary.closing_balance > 0 ? 'مدين (له علينا)' : statement.summary.closing_balance < 0 ? 'دائن (لنا عليه)' : 'متوازن'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Transactions Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>الحركات</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {statement.transactions.length === 0 ? (
                                <div className="text-center py-8 text-muted-foreground">
                                    لا توجد حركات في هذه الفترة
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>التاريخ</TableHead>
                                            <TableHead>النوع</TableHead>
                                            <TableHead>المرجع</TableHead>
                                            <TableHead>البيان</TableHead>
                                            <TableHead className="text-left">مدين</TableHead>
                                            <TableHead className="text-left">دائن</TableHead>
                                            <TableHead className="text-left">الرصيد</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {statement.transactions.map((tx) => (
                                            <TableRow key={`${tx.type}-${tx.id}`}>
                                                <TableCell>{formatDate(tx.date)}</TableCell>
                                                <TableCell>
                                                    <Badge variant={
                                                        tx.type === 'invoice' ? 'destructive'
                                                            : tx.type === 'collection' ? 'default'
                                                                : 'secondary'
                                                    }>
                                                        {tx.type === 'invoice' ? 'فاتورة'
                                                            : tx.type === 'collection' ? 'تحصيل'
                                                                : 'رصيد افتتاحي'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>{tx.reference}</TableCell>
                                                <TableCell>{tx.description}</TableCell>
                                                <TableCell className="text-left money text-red-600">
                                                    {tx.debit > 0 ? formatCurrency(tx.debit) : '-'}
                                                </TableCell>
                                                <TableCell className="text-left money text-green-600">
                                                    {tx.credit > 0 ? formatCurrency(tx.credit) : '-'}
                                                </TableCell>
                                                <TableCell className="text-left money font-medium">
                                                    {formatCurrency(tx.balance)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
