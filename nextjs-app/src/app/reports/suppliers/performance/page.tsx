'use client';

import { useState } from 'react';
import { Download, Building2, TrendingUp, Clock, AlertTriangle } from 'lucide-react';
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
import type { ApiResponse } from '@/types/api';

interface SupplierPerformance {
    supplier_id: number;
    supplier_code: string;
    supplier_name: string;
    shipments_count: number;
    total_sales: number;
    total_wastage: number;
    wastage_rate: number;
    avg_days_to_settle: number;
}

interface SupplierPerformanceReport {
    period: { from: string | null; to: string | null };
    suppliers: SupplierPerformance[];
    summary: {
        total_suppliers: number;
        total_shipments: number;
        total_sales: number;
        avg_wastage_rate: number;
        avg_settlement_days: number;
    };
}

export default function SupplierPerformancePage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'supplier-performance', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<SupplierPerformanceReport>>(
            `${endpoints.reports.supplierPerformance}${queryString ? `?${queryString}` : ''}`
        ),
    });
    const report = data?.data;

    const getWastageColor = (rate: number) => {
        if (rate <= 2) return 'text-green-600';
        if (rate <= 5) return 'text-yellow-600';
        return 'text-red-600';
    };

    const getWastageBadge = (rate: number) => {
        if (rate <= 2) return <Badge className="bg-green-500">Excellent</Badge>;
        if (rate <= 5) return <Badge className="bg-yellow-500">Average</Badge>;
        return <Badge variant="destructive">High</Badge>;
    };

    const handleDownloadPdf = () => {
        const pdfParams = new URLSearchParams();
        if (dateFrom) pdfParams.append('date_from', dateFrom);
        if (dateTo) pdfParams.append('date_to', dateTo);
        window.open(`${process.env.NEXT_PUBLIC_API_URL}${endpoints.reports.supplierPerformancePdf}?${pdfParams}`, '_blank');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Supplier Performance</h1>
                    <p className="text-muted-foreground">Sales, wastage, and settlement analysis by supplier</p>
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
                    <div className="grid gap-4 md:grid-cols-5">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Suppliers</CardTitle>
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_suppliers}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Shipments</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_shipments}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Sales</CardTitle>
                                <TrendingUp className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.summary.total_sales)}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Avg Wastage</CardTitle>
                                <AlertTriangle className={`h-4 w-4 ${getWastageColor(report.summary.avg_wastage_rate)}`} />
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${getWastageColor(report.summary.avg_wastage_rate)}`}>
                                    {report.summary.avg_wastage_rate.toFixed(1)}%
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Avg Settlement</CardTitle>
                                <Clock className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.avg_settlement_days.toFixed(0)} days</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Suppliers Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier Performance Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.suppliers.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead className="text-center">Shipments</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="text-center">Wastage</TableHead>
                                            <TableHead className="text-center">Avg Days</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.suppliers.map((supplier) => (
                                            <TableRow key={supplier.supplier_id}>
                                                <TableCell>
                                                    <div className="font-medium">{supplier.supplier_name}</div>
                                                    <div className="text-sm text-muted-foreground">{supplier.supplier_code}</div>
                                                </TableCell>
                                                <TableCell className="text-center">{supplier.shipments_count}</TableCell>
                                                <TableCell className="text-right font-bold">{formatCurrency(supplier.total_sales)}</TableCell>
                                                <TableCell className="text-center">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className={getWastageColor(supplier.wastage_rate)}>
                                                            {supplier.wastage_rate.toFixed(1)}%
                                                        </span>
                                                        {getWastageBadge(supplier.wastage_rate)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">{supplier.avg_days_to_settle.toFixed(0)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No performance data available</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
