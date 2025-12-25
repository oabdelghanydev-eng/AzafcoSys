'use client';

import { Download, Users, AlertTriangle, Clock, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useCustomerAgingReport } from '@/hooks/api/use-reports';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import { formatCurrency } from '@/lib/utils';
import { endpoints } from '@/lib/api/endpoints';

export default function CustomerAgingPage() {
    const { data, isLoading, error } = useCustomerAgingReport();
    const report = data?.data;
    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleDownloadPdf = () => {
        downloadPdf(endpoints.reports.customerAgingPdf, 'customer-aging-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Customer Aging Report</h1>
                    <p className="text-muted-foreground">Outstanding invoices by age</p>
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
                    {/* Aging Summary */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">0-30 Days (Current)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.totals.current)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-yellow-50 border-yellow-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-yellow-700">31-60 Days</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-yellow-600">
                                    {formatCurrency(report.totals.days_31_60)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-orange-50 border-orange-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-orange-700">61-90 Days</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-600">
                                    {formatCurrency(report.totals.days_61_90)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-red-50 border-red-200">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-red-700">90+ Days (Overdue)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {formatCurrency(report.totals.over_90)}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Summary Stats */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_customers}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Debt</CardTitle>
                                <AlertTriangle className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{formatCurrency(report.summary.total_debt)}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Overdue %</CardTitle>
                                <Clock className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{report.summary.overdue_percentage}%</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Customer Details Table */}
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
                                            <TableHead className="text-right text-green-600">0-30</TableHead>
                                            <TableHead className="text-right text-yellow-600">31-60</TableHead>
                                            <TableHead className="text-right text-orange-600">61-90</TableHead>
                                            <TableHead className="text-right text-red-600">90+</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.customers.map((customer) => (
                                            <TableRow key={customer.customer_id}>
                                                <TableCell>
                                                    <div className="font-medium">{customer.customer_name}</div>
                                                    <div className="text-sm text-muted-foreground">{customer.customer_code}</div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {customer.aging.current > 0 ? formatCurrency(customer.aging.current) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {customer.aging.days_31_60 > 0 ? formatCurrency(customer.aging.days_31_60) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {customer.aging.days_61_90 > 0 ? formatCurrency(customer.aging.days_61_90) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right text-red-600 font-medium">
                                                    {customer.aging.over_90 > 0 ? formatCurrency(customer.aging.over_90) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right font-bold">
                                                    {formatCurrency(customer.total_balance)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No outstanding debts</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
