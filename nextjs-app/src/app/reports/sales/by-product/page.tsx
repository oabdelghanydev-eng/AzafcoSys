'use client';

import { useState } from 'react';
import { Download, Package, BarChart3, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useSalesByProductReport } from '@/hooks/api/use-reports';
import { usePdfDownload } from '@/hooks/use-pdf-download';
import { formatCurrency } from '@/lib/utils';
import { endpoints } from '@/lib/api/endpoints';

export default function SalesByProductPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const { downloadPdf, isDownloading } = usePdfDownload();

    const { data, isLoading, error } = useSalesByProductReport(dateFrom, dateTo);
    const report = data?.data;

    const handleDownloadPdf = () => {
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        downloadPdf(`${endpoints.reports.salesByProductPdf}?${params}`, 'sales-by-product-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Sales by Product</h1>
                    <p className="text-muted-foreground">Product-wise sales analysis</p>
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
                                <CardTitle className="text-sm font-medium">Products</CardTitle>
                                <Package className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_products}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Quantity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_quantity.toLocaleString()}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Weight</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_weight.toLocaleString()} kg</div>
                            </CardContent>
                        </Card>

                        <Card className="bg-primary/5">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
                                <BarChart3 className="h-4 w-4 text-primary" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-primary">{formatCurrency(report.summary.total_revenue)}</div>
                                <p className="text-xs text-muted-foreground">Avg: {formatCurrency(report.summary.avg_price_per_kg)}/kg</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Products Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Products</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.products.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-right">Quantity</TableHead>
                                            <TableHead className="text-right">Weight (kg)</TableHead>
                                            <TableHead className="text-right">Invoices</TableHead>
                                            <TableHead className="text-right">Revenue</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.products.map((product) => (
                                            <TableRow key={product.product_id}>
                                                <TableCell className="font-medium">{product.product_name}</TableCell>
                                                <TableCell className="text-right">{product.quantity.toLocaleString()}</TableCell>
                                                <TableCell className="text-right">{product.weight.toLocaleString()}</TableCell>
                                                <TableCell className="text-right">{product.invoices_count}</TableCell>
                                                <TableCell className="text-right font-bold">{formatCurrency(product.revenue)}</TableCell>
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
