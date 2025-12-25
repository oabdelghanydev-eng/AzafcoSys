'use client';

import { useState } from 'react';
import { Download, ArrowUpRight, ArrowDownRight, RefreshCw, Package } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type { ApiResponse } from '@/types/api';

interface ProductMovement {
    product_id: number;
    product_name: string;
    incoming: { cartons: number; weight: number };
    outgoing: { cartons: number; weight: number };
    carryover: { in: number; out: number };
    net_change: { cartons: number; weight: number };
}

interface StockMovementReport {
    period: { from: string | null; to: string | null };
    products: ProductMovement[];
    totals: {
        incoming: { cartons: number; weight: number };
        outgoing: { cartons: number; weight: number };
        carryover: { in: number; out: number };
        net_change: { cartons: number; weight: number };
    };
    summary: {
        products_count: number;
        shipments_received: number;
        invoices_issued: number;
    };
}

export default function StockMovementPage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'stock-movement', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<StockMovementReport>>(
            `${endpoints.reports.inventoryMovement}${queryString ? `?${queryString}` : ''}`
        ),
    });
    const report = data?.data;

    const handleDownloadPdf = () => {
        const pdfParams = new URLSearchParams();
        if (dateFrom) pdfParams.append('date_from', dateFrom);
        if (dateTo) pdfParams.append('date_to', dateTo);
        window.open(`${process.env.NEXT_PUBLIC_API_URL}${endpoints.reports.inventoryMovementPdf}?${pdfParams}`, '_blank');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Stock Movement</h1>
                    <p className="text-muted-foreground">Inventory in/out movements by product</p>
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
                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">Incoming</CardTitle>
                                <ArrowDownRight className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {report.totals.incoming.cartons.toLocaleString()} cartons
                                </div>
                                <p className="text-xs text-green-500">
                                    {report.totals.incoming.weight.toLocaleString()} kg
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="bg-red-50 border-red-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-red-700">Outgoing (Sales)</CardTitle>
                                <ArrowUpRight className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {report.totals.outgoing.cartons.toLocaleString()} cartons
                                </div>
                                <p className="text-xs text-red-500">
                                    {report.totals.outgoing.weight.toLocaleString()} kg
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="bg-blue-50 border-blue-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-blue-700">Carryover</CardTitle>
                                <RefreshCw className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-lg font-bold text-blue-600">
                                    In: {report.totals.carryover.in.toLocaleString()}
                                </div>
                                <div className="text-lg font-bold text-blue-600">
                                    Out: {report.totals.carryover.out.toLocaleString()}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className={report.totals.net_change.cartons >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Net Change</CardTitle>
                                <Package className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${report.totals.net_change.cartons >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {report.totals.net_change.cartons >= 0 ? '+' : ''}{report.totals.net_change.cartons.toLocaleString()}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {report.totals.net_change.weight.toLocaleString()} kg
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Products Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Movement by Product</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.products.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-center text-green-600">Incoming</TableHead>
                                            <TableHead className="text-center text-red-600">Outgoing</TableHead>
                                            <TableHead className="text-center text-blue-600">Carryover</TableHead>
                                            <TableHead className="text-center">Net Change</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.products.map((product) => (
                                            <TableRow key={product.product_id}>
                                                <TableCell className="font-medium">{product.product_name}</TableCell>
                                                <TableCell className="text-center text-green-600">
                                                    <div>{product.incoming.cartons} ctn</div>
                                                    <div className="text-xs">{product.incoming.weight.toLocaleString()} kg</div>
                                                </TableCell>
                                                <TableCell className="text-center text-red-600">
                                                    <div>{product.outgoing.cartons} ctn</div>
                                                    <div className="text-xs">{product.outgoing.weight.toLocaleString()} kg</div>
                                                </TableCell>
                                                <TableCell className="text-center text-blue-600">
                                                    <div>In: {product.carryover.in}</div>
                                                    <div>Out: {product.carryover.out}</div>
                                                </TableCell>
                                                <TableCell className="text-center font-bold">
                                                    <span className={product.net_change.cartons >= 0 ? 'text-green-600' : 'text-red-600'}>
                                                        {product.net_change.cartons >= 0 ? '+' : ''}{product.net_change.cartons}
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No movements in this period</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
