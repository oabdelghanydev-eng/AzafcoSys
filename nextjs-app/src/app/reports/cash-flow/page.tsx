'use client';

import { useState } from 'react';
import { Download, ArrowUpRight, ArrowDownRight, Wallet, Building2, Loader2, Printer } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useCashFlowReport } from '@/hooks/api/use-reports';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import { DateRangePicker } from '@/components/shared/date-range-picker';
import { formatCurrency } from '@/lib/utils';
import { endpoints } from '@/lib/api/endpoints';

export default function CashFlowPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const { downloadPdf, isDownloading } = usePdfDownload();

    const { data, isLoading, error } = useCashFlowReport(dateFrom, dateTo);
    const report = data?.data;

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        downloadPdf(`${endpoints.reports.cashFlowPdf}?${params}`, 'cash-flow-report');
    };

    const handlePrint = () => window.print();

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Cash Flow Report</h1>
                    <p className="text-muted-foreground">Cash inflows and outflows analysis</p>
                </div>
                <div className="flex gap-2 no-print">
                    <Button variant="outline" onClick={handlePrint} disabled={!report}>
                        <Printer className="mr-2 h-4 w-4" />
                        Print
                    </Button>
                    <Button onClick={handleDownloadPdf} disabled={!report || isDownloading}>
                        {isDownloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                        {isDownloading ? 'Downloading...' : 'PDF'}
                    </Button>
                </div>
            </div>

            {/* Date Range Picker */}
            <div className="no-print">
                <DateRangePicker
                    dateFrom={dateFrom}
                    dateTo={dateTo}
                    onDateFromChange={setDateFrom}
                    onDateToChange={setDateTo}
                />
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
                    {/* Account Balances */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Cashbox Balance</CardTitle>
                                <Wallet className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-green-600">
                                    {formatCurrency(report.account_balances.cashbox)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-blue-50 border-blue-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Bank Balance</CardTitle>
                                <Building2 className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-blue-600">
                                    {formatCurrency(report.account_balances.bank)}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Flow Summary */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Inflows</CardTitle>
                                <ArrowUpRight className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    +{formatCurrency(report.summary.total_inflows)}
                                </div>
                                <div className="text-xs text-muted-foreground mt-2">
                                    <p>Cash: {formatCurrency(report.inflows.by_payment_method.cash)}</p>
                                    <p>Bank: {formatCurrency(report.inflows.by_payment_method.bank)}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Outflows</CardTitle>
                                <ArrowDownRight className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    -{formatCurrency(report.summary.total_outflows)}
                                </div>
                                <div className="text-xs text-muted-foreground mt-2">
                                    <p>Company: {formatCurrency(report.outflows.by_type.company_expenses)}</p>
                                    <p>Supplier Exp: {formatCurrency(report.outflows.by_type.supplier_expenses)}</p>
                                    <p>Supplier Pay: {formatCurrency(report.outflows.by_type.supplier_payments)}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className={report.summary.net_flow >= 0 ? 'border-green-200' : 'border-red-200'}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Net Cash Flow</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${report.summary.net_flow >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(report.summary.net_flow)}
                                </div>
                                <p className="text-xs text-muted-foreground mt-2">
                                    Total Liquidity: {formatCurrency(report.summary.total_liquidity)}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </div>
    );
}
