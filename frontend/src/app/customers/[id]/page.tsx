'use client';

import { useParams, useRouter } from 'next/navigation';
import { useCustomer, useCustomerStatement } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { formatCurrency, formatDate } from '@/lib/format';
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

interface StatementEntry {
    date: string;
    type: 'invoice' | 'collection';
    reference: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
}

export default function CustomerStatementPage() {
    const params = useParams();
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const customerId = Number(params.id);

    const { data: customer, isLoading: customerLoading } = useCustomer(customerId);
    const { data: statement, isLoading: statementLoading } = useCustomerStatement(customerId);

    if (authLoading || customerLoading) {
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

    // Process statement data into chronological entries
    const processStatement = (): StatementEntry[] => {
        const entries: StatementEntry[] = [];
        let runningBalance = 0;

        // Get invoices and collections from statement
        const invoices = statement?.invoices || [];
        const collections = statement?.collections || [];

        // Combine and sort by date
        const allItems = [
            ...invoices.map((inv: any) => ({
                date: inv.date,
                type: 'invoice' as const,
                reference: inv.invoice_number,
                description: `فاتورة ${inv.type === 'sale' ? 'بيع' : 'مرتجع'}`,
                amount: inv.total,
                originalData: inv,
            })),
            ...collections.map((col: any) => ({
                date: col.date,
                type: 'collection' as const,
                reference: col.receipt_number,
                description: `تحصيل ${col.payment_method === 'cash' ? 'نقدي' : 'بنكي'}`,
                amount: col.amount,
                originalData: col,
            })),
        ].sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

        // Calculate running balance
        for (const item of allItems) {
            if (item.type === 'invoice') {
                runningBalance += item.amount;
                entries.push({
                    date: item.date,
                    type: 'invoice',
                    reference: item.reference,
                    description: item.description,
                    debit: item.amount,
                    credit: 0,
                    balance: runningBalance,
                });
            } else {
                runningBalance -= item.amount;
                entries.push({
                    date: item.date,
                    type: 'collection',
                    reference: item.reference,
                    description: item.description,
                    debit: 0,
                    credit: item.amount,
                    balance: runningBalance,
                });
            }
        }

        return entries;
    };

    const entries = processStatement();
    const isLoading = statementLoading;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">كشف حساب</h1>
                        <p className="text-gray-600">{customer?.name}</p>
                    </div>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={() => router.push('/customers')}>
                            رجوع
                        </Button>
                        <Button variant="outline" onClick={() => window.print()}>
                            طباعة
                        </Button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                {/* Customer Summary */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm text-gray-500">اسم العميل</div>
                            <div className="text-xl font-bold">{customer?.name}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm text-gray-500">رقم الهاتف</div>
                            <div className="text-xl font-bold">{customer?.phone || '-'}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm text-gray-500">الرصيد الحالي</div>
                            <div
                                className={`text-2xl font-bold ${(customer?.balance || 0) > 0 ? 'text-red-600' : 'text-green-600'
                                    }`}
                            >
                                {formatCurrency(Math.abs(customer?.balance || 0))}
                                <span className="text-sm font-normal mr-1">
                                    ({(customer?.balance || 0) > 0 ? 'مديون' : 'دائن'})
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Statement Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>كشف الحساب الزمني</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                        ) : entries.length === 0 ? (
                            <div className="p-8 text-center text-gray-500">لا توجد حركات</div>
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
                                    {entries.map((entry, index) => (
                                        <TableRow key={index}>
                                            <TableCell>{formatDate(entry.date)}</TableCell>
                                            <TableCell>
                                                <span
                                                    className={`px-2 py-1 rounded text-xs font-medium ${entry.type === 'invoice'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-green-100 text-green-800'
                                                        }`}
                                                >
                                                    {entry.type === 'invoice' ? 'فاتورة' : 'تحصيل'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">{entry.reference}</TableCell>
                                            <TableCell>{entry.description}</TableCell>
                                            <TableCell className="text-left text-red-600 font-medium">
                                                {entry.debit > 0 ? formatCurrency(entry.debit) : '-'}
                                            </TableCell>
                                            <TableCell className="text-left text-green-600 font-medium">
                                                {entry.credit > 0 ? formatCurrency(entry.credit) : '-'}
                                            </TableCell>
                                            <TableCell
                                                className={`text-left font-bold ${entry.balance > 0 ? 'text-red-600' : 'text-green-600'
                                                    }`}
                                            >
                                                {formatCurrency(Math.abs(entry.balance))}
                                                {entry.balance !== 0 && (
                                                    <span className="text-xs font-normal mr-1">
                                                        {entry.balance > 0 ? 'م' : 'د'}
                                                    </span>
                                                )}
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
