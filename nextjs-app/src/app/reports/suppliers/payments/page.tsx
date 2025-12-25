'use client';

import { useState } from 'react';
import { Download, Wallet, Building2, Receipt, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { formatCurrency } from '@/lib/utils';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import type { ApiResponse } from '@/types/api';

interface SupplierPaymentItem {
    supplier_id: number;
    supplier_code: string;
    supplier_name: string;
    payments: number;
    expenses: number;
    total: number;
    transactions_count: number;
}

interface SupplierPaymentsReport {
    period: { from: string | null; to: string | null };
    suppliers: SupplierPaymentItem[];
    totals: {
        total_payments: number;
        total_expenses: number;
        grand_total: number;
    };
    summary: {
        suppliers_count: number;
        transactions_count: number;
    };
}

export default function SupplierPaymentsPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'supplier-payments', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<SupplierPaymentsReport>>(
            `${endpoints.reports.supplierPayments}${queryString ? `?${queryString}` : ''}`
        ),
    });
    const report = data?.data;

    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleDownloadPdf = () => {
        const pdfParams = new URLSearchParams();
        if (dateFrom) pdfParams.append('date_from', dateFrom);
        if (dateTo) pdfParams.append('date_to', dateTo);
        downloadPdf(`${endpoints.reports.supplierPaymentsPdf}?${pdfParams}`, 'supplier-payments-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Supplier Payments</h1>
                    <p className="text-muted-foreground">Payments and expenses by supplier</p>
                </div>
                <Button onClick={handleDownloadPdf} disabled={!report || isDownloading}>
                    {isDownloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                    {isDownloading ? 'Downloading...' : 'Download PDF'}
                </Button>
            </div>

            {/* Date Filters */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Filter by Date</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex gap-4 flex-wrap">
                        <div className="space-y-2">
                            <Label>From</Label>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>To</Label>
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
                <div className="flex justify-center p-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            )}

            {error && (
                <Card className="border-destructive">
                    <CardContent className="pt-6">
                        <p className="text-destructive">Failed to load report. Please try again.</p>
                    </CardContent>
                </Card>
            )}

            {report && (
                <>
                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Suppliers</CardTitle>
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.suppliers_count}</div>
                            </CardContent>
                        </Card>

                        <Card className="bg-blue-50 border-blue-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-blue-700">Direct Payments</CardTitle>
                                <Wallet className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-600">
                                    {formatCurrency(report.totals.total_payments)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-orange-50 border-orange-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-orange-700">Expenses</CardTitle>
                                <Receipt className="h-4 w-4 text-orange-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-600">
                                    {formatCurrency(report.totals.total_expenses)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-purple-50 border-purple-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-purple-700">Grand Total</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-purple-600">
                                    {formatCurrency(report.totals.grand_total)}
                                </div>
                                <p className="text-xs text-purple-500">{report.summary.transactions_count} transactions</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Suppliers Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Details by Supplier</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.suppliers.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead className="text-center">Transactions</TableHead>
                                            <TableHead className="text-right">Payments</TableHead>
                                            <TableHead className="text-right">Expenses</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.suppliers.map((supplier) => (
                                            <TableRow key={supplier.supplier_id}>
                                                <TableCell>
                                                    <div className="font-medium">{supplier.supplier_name}</div>
                                                    <div className="text-sm text-muted-foreground">{supplier.supplier_code}</div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="secondary">{supplier.transactions_count}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right text-blue-600">
                                                    {supplier.payments > 0 ? formatCurrency(supplier.payments) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right text-orange-600">
                                                    {supplier.expenses > 0 ? formatCurrency(supplier.expenses) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right font-bold">
                                                    {formatCurrency(supplier.total)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No payments in this period</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
