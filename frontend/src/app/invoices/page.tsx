'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useInvoices, useCancelInvoice } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useAuth';
import { formatCurrency, formatDate } from '@/lib/format';
import { extractData } from '@/lib/helpers';
import { PageHeader, PageContainer } from '@/components/layout/PageHeader';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function InvoicesPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: invoices, isLoading } = useInvoices();
    const cancelInvoice = useCancelInvoice();

    const [searchTerm, setSearchTerm] = useState('');
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<number | null>(null);

    // Auth check
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

    const handleCancel = async () => {
        if (selectedInvoice) {
            await cancelInvoice.mutateAsync(selectedInvoice);
            setCancelDialogOpen(false);
            setSelectedInvoice(null);
        }
    };

    const invoiceList = extractData(invoices);
    const filteredInvoices = invoiceList.filter((inv) =>
        inv.invoice_number?.includes(searchTerm) ||
        inv.customer?.name?.includes(searchTerm)
    );

    return (
        <>
            <PageHeader
                title="الفواتير"
                actions={
                    <Button onClick={() => router.push('/invoices/new')}>
                        + فاتورة جديدة
                    </Button>
                }
            />
            <PageContainer>
                {/* Search */}
                <div className="mb-6">
                    <Input
                        placeholder="بحث برقم الفاتورة أو اسم العميل..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                {/* Table */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {isLoading ? (
                        <div className="p-8 text-center text-gray-500">جاري التحميل...</div>
                    ) : filteredInvoices.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">لا توجد فواتير</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>رقم الفاتورة</TableHead>
                                    <TableHead>العميل</TableHead>
                                    <TableHead>التاريخ</TableHead>
                                    <TableHead>الإجمالي</TableHead>
                                    <TableHead>المدفوع</TableHead>
                                    <TableHead>المتبقي</TableHead>
                                    <TableHead>الحالة</TableHead>
                                    <TableHead>الإجراءات</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredInvoices.map((invoice: any) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                                        <TableCell>{invoice.customer?.name}</TableCell>
                                        <TableCell>{formatDate(invoice.date)}</TableCell>
                                        <TableCell>{formatCurrency(invoice.total)}</TableCell>
                                        <TableCell>{formatCurrency(invoice.paid_amount)}</TableCell>
                                        <TableCell>{formatCurrency(invoice.balance)}</TableCell>
                                        <TableCell>
                                            {invoice.status === 'active' ? (
                                                invoice.balance > 0 ? (
                                                    <Badge variant="secondary">غير مسددة</Badge>
                                                ) : (
                                                    <Badge className="bg-green-100 text-green-800">مسددة</Badge>
                                                )
                                            ) : (
                                                <Badge variant="destructive">ملغاة</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {invoice.status === 'active' && (
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => {
                                                        setSelectedInvoice(invoice.id);
                                                        setCancelDialogOpen(true);
                                                    }}
                                                >
                                                    إلغاء
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            </PageContainer>

            {/* Cancel Dialog */}
            <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>تأكيد إلغاء الفاتورة</DialogTitle>
                        <DialogDescription>
                            هل أنت متأكد من إلغاء هذه الفاتورة؟ سيتم إرجاع الكميات للمخزون وتحديث رصيد العميل.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCancelDialogOpen(false)}>
                            تراجع
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleCancel}
                            disabled={cancelInvoice.isPending}
                        >
                            {cancelInvoice.isPending ? 'جاري الإلغاء...' : 'تأكيد الإلغاء'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
