'use client';

import { useState } from 'react';
import { Download, ArrowUpRight, ArrowDownRight, Wallet, Building2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCashFlowReport } from '@/hooks/api/use-reports';
import { formatCurrency } from '@/lib/utils';
import { endpoints } from '@/lib/api/endpoints';

export default function CashFlowPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const { data, isLoading, error } = useCashFlowReport(dateFrom, dateTo);
    const report = data?.data;

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        window.open(`${process.env.NEXT_PUBLIC_API_URL}${endpoints.reports.cashFlowPdf}?${params}`, '_blank');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Cash Flow Report</h1>
                    <p className="text-muted-foreground">Cash inflows and outflows analysis</p>
                </div>
                <Button onClick={handleDownloadPdf} disabled={!report}>
                    <Download className="mr-2 h-4 w-4" />
                    Download PDF
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
