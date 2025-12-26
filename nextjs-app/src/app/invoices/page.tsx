'use client';

import Link from 'next/link';
import { Plus, Search, FileText, Filter } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { EmptyState } from '@/components/shared/empty-state';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useInvoices } from '@/hooks/api/use-invoices';
import type { Invoice } from '@/types/api';

function getStatusBadge(status: string) {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
        paid: { variant: 'default', label: 'مدفوع' },
        partially_paid: { variant: 'secondary', label: 'جزئي' },
        unpaid: { variant: 'outline', label: 'غير مدفوع' },
        cancelled: { variant: 'destructive', label: 'ملغي' },
        active: { variant: 'default', label: 'نشط' },
    };

    const config = variants[status] || { variant: 'outline', label: status };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

// Mobile card view
function InvoiceCard({ invoice }: { invoice: Invoice }) {
    return (
        <Link href={`/invoices/${invoice.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{invoice.invoice_number}</p>
                            <p className="text-sm text-muted-foreground">{invoice.customer?.name}</p>
                        </div>
                        {getStatusBadge(invoice.status)}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{formatDateShort(invoice.date)}</span>
                        <span className="font-semibold money">{formatMoney(invoice.total)}</span>
                    </div>
                    {invoice.balance > 0 && (
                        <p className="text-xs text-orange-600 mt-1">
                            المتبقي: {formatMoney(invoice.balance)}
                        </p>
                    )}
                </CardContent>
            </Card>
        </Link>
    );
}

export default function InvoicesPage() {
    const { data, isLoading, error, refetch } = useInvoices();

    const invoices = data?.data || [];
    const isEmpty = invoices.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading invoices..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load invoices"
                message="Could not fetch invoices from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Page Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">الفواتير</h1>
                    <p className="text-muted-foreground">
                        إدارة فواتير المبيعات
                    </p>
                </div>
                <PermissionGate permission="invoices.create">
                    <Button asChild className="touch-target">
                        <Link href="/invoices/new">
                            <Plus className="mr-2 h-4 w-4" />
                            فاتورة جديدة
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            {/* Filters */}
            <div className="flex flex-col sm:flex-row gap-3">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input placeholder="بحث في الفواتير..." className="pl-10" />
                </div>
                <Button variant="outline" className="touch-target">
                    <Filter className="mr-2 h-4 w-4" />
                    فلترة
                </Button>
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<FileText className="h-12 w-12" />}
                    title="لا توجد فواتير"
                    description="أنشئ فاتورة جديدة للبدء"
                    action={{ label: 'فاتورة جديدة', href: '/invoices/new' }}
                />
            ) : (
                <>
                    {/* Mobile Card View */}
                    <div className="grid gap-3 lg:hidden">
                        {invoices.map((invoice: Invoice) => (
                            <InvoiceCard key={invoice.id} invoice={invoice} />
                        ))}
                    </div>

                    {/* Desktop Table View */}
                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>رقم الفاتورة</TableHead>
                                    <TableHead>العميل</TableHead>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead className="text-right">الإجمالي</TableHead>
                                    <TableHead className="text-right">المدفوع</TableHead>
                                    <TableHead className="text-right">المتبقي</TableHead>
                                    <TableHead>الحالة</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((invoice: Invoice) => (
                                    <TableRow key={invoice.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>
                                            <Link href={`/invoices/${invoice.id}`} className="font-medium hover:underline">
                                                {invoice.invoice_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{invoice.customer?.name}</TableCell>
                                        <TableCell>{formatDateShort(invoice.date)}</TableCell>
                                        <TableCell className="text-right money">{formatMoney(invoice.total)}</TableCell>
                                        <TableCell className="text-right money">{formatMoney(invoice.paid)}</TableCell>
                                        <TableCell className="text-right money">
                                            {invoice.balance > 0 ? (
                                                <span className="text-orange-600">{formatMoney(invoice.balance)}</span>
                                            ) : (
                                                formatMoney(0)
                                            )}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(invoice.status)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </>
            )}
        </div>
    );
}
