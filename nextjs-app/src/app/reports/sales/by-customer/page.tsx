'use client';

import { useState } from 'react';
import { Download, Users, TrendingUp, Receipt, Wallet, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { formatCurrency } from '@/lib/utils';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import type { ApiResponse } from '@/types/api';

interface CustomerSales {
    customer_id: number;
    customer_code: string;
    customer_name: string;
    invoices_count: number;
    total_sales: number;
    total_paid: number;
    total_balance: number;
    avg_invoice_value: number;
}

interface SalesByCustomerReport {
    period: { from: string | null; to: string | null };
    customers: CustomerSales[];
    summary: {
        total_customers: number;
        total_invoices: number;
        total_sales: number;
        total_collected: number;
        total_outstanding: number;
    };
}

export default function SalesByCustomerPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'sales-by-customer', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<SalesByCustomerReport>>(
            `${endpoints.reports.salesByCustomer}${queryString ? `?${queryString}` : ''}`
        ),
    });
    const report = data?.data;

    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleDownloadPdf = () => {
        const pdfParams = new URLSearchParams();
        if (dateFrom) pdfParams.append('date_from', dateFrom);
        if (dateTo) pdfParams.append('date_to', dateTo);
        downloadPdf(`${endpoints.reports.salesByCustomerPdf}?${pdfParams}`, 'sales-by-customer-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Sales by Customer</h1>
                    <p className="text-muted-foreground">Customer-wise sales analysis</p>
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
                    <div className="grid gap-4 md:grid-cols-5">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Customers</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_customers}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Invoices</CardTitle>
                                <Receipt className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_invoices}</div>
                            </CardContent>
                        </Card>

                        <Card className="bg-blue-50 border-blue-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-blue-700">Total Sales</CardTitle>
                                <TrendingUp className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-600">
                                    {formatCurrency(report.summary.total_sales)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">Collected</CardTitle>
                                <Wallet className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.summary.total_collected)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-orange-50 border-orange-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-orange-700">Outstanding</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-600">
                                    {formatCurrency(report.summary.total_outstanding)}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Customers Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Customer Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.customers.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Customer</TableHead>
                                            <TableHead className="text-center">Invoices</TableHead>
                                            <TableHead className="text-right">Total Sales</TableHead>
                                            <TableHead className="text-right">Paid</TableHead>
                                            <TableHead className="text-right">Balance</TableHead>
                                            <TableHead className="text-right">Avg Invoice</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.customers.map((customer) => (
                                            <TableRow key={customer.customer_id}>
                                                <TableCell>
                                                    <div className="font-medium">{customer.customer_name}</div>
                                                    <div className="text-sm text-muted-foreground">{customer.customer_code}</div>
                                                </TableCell>
                                                <TableCell className="text-center">{customer.invoices_count}</TableCell>
                                                <TableCell className="text-right font-bold">{formatCurrency(customer.total_sales)}</TableCell>
                                                <TableCell className="text-right text-green-600">{formatCurrency(customer.total_paid)}</TableCell>
                                                <TableCell className={`text-right font-bold ${customer.total_balance > 0 ? 'text-orange-600' : 'text-green-600'}`}>
                                                    {formatCurrency(customer.total_balance)}
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground">
                                                    {formatCurrency(customer.avg_invoice_value)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No sales in this period</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
