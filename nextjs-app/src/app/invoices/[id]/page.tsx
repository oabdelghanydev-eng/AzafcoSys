'use client';

import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Printer, X, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort, formatQuantity } from '@/lib/formatters';
import { useInvoice, useCancelInvoice } from '@/hooks/api/use-invoices';
import { useState } from 'react';
import { usePdfDownload } from '@/hooks/use-pdf-download';

function getStatusBadge(status: string) {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
        paid: { variant: 'default', label: 'Paid' },
        partially_paid: { variant: 'secondary', label: 'Partial' },
        unpaid: { variant: 'outline', label: 'Unpaid' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
    };
    const config = variants[status] || { variant: 'outline', label: status };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

export default function InvoiceDetailPage() {
    const params = useParams();
    const _router = useRouter();
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);

    const invoiceId = Number(params.id);
    const { data: invoice, isLoading, error, refetch } = useInvoice(invoiceId);
    const cancelInvoice = useCancelInvoice();
    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleCancel = async () => {
        try {
            await cancelInvoice.mutateAsync(invoiceId);
            toast.success('Invoice cancelled');
            setShowCancelConfirm(false);
            refetch();
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to cancel invoice');
        }
    };

    const handlePrint = () => {
        downloadPdf(`/invoices/${invoiceId}/pdf`, `invoice-${invoice?.invoice_number || invoiceId}.pdf`);
    };

    if (isLoading) {
        return <LoadingState message="Loading invoice..." />;
    }

    if (error || !invoice) {
        return (
            <ErrorState
                title="Failed to load invoice"
                message="Invoice not found or could not be loaded"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/invoices">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{invoice.invoice_number}</h1>
                            {getStatusBadge(invoice.status)}
                        </div>
                        <p className="text-muted-foreground">{formatDateShort(invoice.date)}</p>
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button variant="outline" onClick={handlePrint} disabled={isDownloading} className="touch-target">
                        {isDownloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Printer className="mr-2 h-4 w-4" />}
                        {isDownloading ? 'Downloading...' : 'Print'}
                    </Button>
                    {invoice.status !== 'cancelled' && (
                        <PermissionGate permission="invoices.cancel">
                            <Button
                                variant="destructive"
                                onClick={() => setShowCancelConfirm(true)}
                                className="touch-target"
                            >
                                <X className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        </PermissionGate>
                    )}
                </div>
            </div>

            {/* Customer & Summary */}
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Customer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="font-semibold text-lg">{invoice.customer?.name}</p>
                        <p className="text-sm text-muted-foreground">{invoice.customer?.phone || '-'}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Subtotal</span>
                            <span className="money">{formatMoney(invoice.subtotal || invoice.total)}</span>
                        </div>
                        {invoice.discount > 0 && (
                            <div className="flex justify-between text-red-600">
                                <span>Discount</span>
                                <span className="money">-{formatMoney(invoice.discount)}</span>
                            </div>
                        )}
                        <div className="flex justify-between font-bold text-lg border-t pt-2">
                            <span>Total</span>
                            <span className="money">{formatMoney(invoice.total)}</span>
                        </div>
                        <div className="flex justify-between text-green-600">
                            <span>Paid</span>
                            <span className="money">{formatMoney(invoice.paid || 0)}</span>
                        </div>
                        {invoice.balance > 0 && (
                            <div className="flex justify-between text-orange-600 font-semibold">
                                <span>Balance</span>
                                <span className="money">{formatMoney(invoice.balance)}</span>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Items */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Items</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead className="text-right">Cartons</TableHead>
                                <TableHead className="text-right">Weight</TableHead>
                                <TableHead className="text-right">Price/KG</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {(invoice.items || []).map((item: { product?: { name?: string }; cartons: number; quantity: number; unit_price: number; subtotal: number }, index: number) => (
                                <TableRow key={index}>
                                    <TableCell className="font-medium">{item.product?.name || 'Product'}</TableCell>
                                    <TableCell className="text-right">{item.cartons}</TableCell>
                                    <TableCell className="text-right">{formatQuantity(item.quantity)}kg</TableCell>
                                    <TableCell className="text-right money">{formatMoney(item.unit_price)}</TableCell>
                                    <TableCell className="text-right money font-semibold">{formatMoney(item.subtotal)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Payments */}
            {((invoice.payments?.length ?? 0) > 0 || (invoice.allocations?.length ?? 0) > 0) && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {(invoice.allocations || invoice.payments || []).map((payment: { collection?: { receipt_number?: string }; id?: number; created_at?: string; date?: string; amount?: number; allocated_amount?: number }, index: number) => (
                                <div key={index} className="flex justify-between py-2 border-b last:border-0">
                                    <div>
                                        <p className="font-medium">Collection #{payment.collection?.receipt_number || payment.id}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDateShort(payment.created_at || payment.date)}
                                        </p>
                                    </div>
                                    <p className="font-semibold text-green-600 money">
                                        {formatMoney(payment.amount)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Cancel Confirmation */}
            <ConfirmDialog
                open={showCancelConfirm}
                onOpenChange={setShowCancelConfirm}
                title="Cancel Invoice?"
                description="This will cancel the invoice and reverse any stock allocations. This action cannot be undone."
                confirmLabel="Cancel Invoice"
                onConfirm={handleCancel}
                variant="destructive"
                loading={cancelInvoice.isPending}
            />
        </div>
    );
}
