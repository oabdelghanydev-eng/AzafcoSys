'use client';

import { useState } from 'react';
import { Download, DollarSign, TrendingUp, TrendingDown, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useProfitLossReport } from '@/hooks/api/use-reports';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import { formatCurrency } from '@/lib/utils';
import { endpoints } from '@/lib/api/endpoints';

export default function ProfitLossPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const { downloadPdf, isDownloading } = usePdfDownload();

    const { data, isLoading, error } = useProfitLossReport(dateFrom, dateTo);
    const report = data?.data;

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        const endpoint = `${endpoints.reports.profitLossPdf}?${params}`;
        downloadPdf(endpoint, 'profit-loss-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Profit & Loss Report</h1>
                    <p className="text-muted-foreground">Revenue, expenses, and net profit analysis</p>
                </div>
                <Button onClick={handleDownloadPdf} disabled={!report || isDownloading}>
                    {isDownloading ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                        <Download className="mr-2 h-4 w-4" />
                    )}
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
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
                                <TrendingUp className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.summary.total_revenue)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Commission @ {report.revenue.commission.commission_rate}%
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Expenses</CardTitle>
                                <TrendingDown className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {formatCurrency(report.summary.total_expenses)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Company expenses only
                                </p>
                            </CardContent>
                        </Card>

                        <Card className={report.summary.net_profit >= 0 ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Net Profit</CardTitle>
                                <DollarSign className={`h-4 w-4 ${report.summary.net_profit >= 0 ? 'text-green-500' : 'text-red-500'}`} />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${report.summary.net_profit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(report.summary.net_profit)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Margin: {report.summary.profit_margin}%
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Expenses Breakdown */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Expenses by Category</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.expenses.by_category.length > 0 ? (
                                <div className="space-y-3">
                                    {report.expenses.by_category.map((cat) => (
                                        <div key={cat.category} className="flex items-center justify-between p-3 bg-muted rounded-lg">
                                            <div>
                                                <p className="font-medium">{cat.category}</p>
                                                <p className="text-sm text-muted-foreground">{cat.count} transactions</p>
                                            </div>
                                            <p className="font-bold text-red-600">{formatCurrency(cat.amount)}</p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No expenses in this period</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
