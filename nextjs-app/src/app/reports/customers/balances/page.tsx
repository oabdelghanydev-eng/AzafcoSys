'use client';

import { Download, Users, TrendingUp, TrendingDown, Scale, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { formatCurrency } from '@/lib/utils';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import type { ApiResponse } from '@/types/api';

interface CustomerBalance {
    customer_id: number;
    customer_code: string;
    customer_name: string;
    balance: number;
    balance_type: 'debtor' | 'creditor' | 'settled';
}

interface CustomerBalancesReport {
    as_of_date: string;
    customers: CustomerBalance[];
    totals: {
        total_debtors: number;
        total_creditors: number;
        net_balance: number;
    };
    summary: {
        total_customers: number;
        debtors_count: number;
        creditors_count: number;
        settled_count: number;
    };
}

export default function CustomerBalancesPage() {
    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'customer-balances'],
        queryFn: () => api.get<ApiResponse<CustomerBalancesReport>>(endpoints.reports.customerBalances),
    });
    const report = data?.data;

    const getBalanceColor = (type: string) => {
        switch (type) {
            case 'debtor': return 'text-orange-600';
            case 'creditor': return 'text-green-600';
            default: return 'text-gray-600';
        }
    };

    const getBalanceBadge = (type: string) => {
        switch (type) {
            case 'debtor': return <Badge className="bg-orange-500">Debtor</Badge>;
            case 'creditor': return <Badge className="bg-green-500">Creditor</Badge>;
            default: return <Badge variant="secondary">Settled</Badge>;
        }
    };

    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleDownloadPdf = () => {
        downloadPdf(endpoints.reports.customerBalancesPdf, 'customer-balances-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Customer Balances</h1>
                    <p className="text-muted-foreground">All customer balances summary</p>
                </div>
                <Button onClick={handleDownloadPdf} disabled={!report || isDownloading}>
                    {isDownloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                    {isDownloading ? 'Downloading...' : 'Download PDF'}
                </Button>
            </div>

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
                                <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_customers}</div>
                                <p className="text-xs text-muted-foreground">
                                    {report.summary.settled_count} settled
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="bg-orange-50 border-orange-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-orange-700">Debtors (They Owe)</CardTitle>
                                <TrendingUp className="h-4 w-4 text-orange-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-600">
                                    {formatCurrency(report.totals.total_debtors)}
                                </div>
                                <p className="text-xs text-orange-500">{report.summary.debtors_count} customers</p>
                            </CardContent>
                        </Card>

                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">Creditors (We Owe)</CardTitle>
                                <TrendingDown className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.totals.total_creditors)}
                                </div>
                                <p className="text-xs text-green-500">{report.summary.creditors_count} customers</p>
                            </CardContent>
                        </Card>

                        <Card className={report.totals.net_balance >= 0 ? 'bg-orange-50 border-orange-200' : 'bg-green-50 border-green-200'}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Net Market Balance</CardTitle>
                                <Scale className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${report.totals.net_balance >= 0 ? 'text-orange-600' : 'text-green-600'}`}>
                                    {formatCurrency(Math.abs(report.totals.net_balance))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {report.totals.net_balance >= 0 ? 'Net receivable' : 'Net payable'}
                                </p>
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
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Balance</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.customers.map((customer) => (
                                            <TableRow key={customer.customer_id}>
                                                <TableCell>
                                                    <div className="font-medium">{customer.customer_name}</div>
                                                    <div className="text-sm text-muted-foreground">{customer.customer_code}</div>
                                                </TableCell>
                                                <TableCell>{getBalanceBadge(customer.balance_type)}</TableCell>
                                                <TableCell className={`text-right font-bold ${getBalanceColor(customer.balance_type)}`}>
                                                    {formatCurrency(Math.abs(customer.balance))}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No customers found</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
