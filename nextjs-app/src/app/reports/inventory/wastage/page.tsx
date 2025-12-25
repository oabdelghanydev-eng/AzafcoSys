'use client';

import { useState } from 'react';
import { Download, AlertTriangle, Package, Truck, Scale } from 'lucide-react';
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

interface ShipmentWastage {
    shipment_id: number;
    shipment_code: string;
    supplier_name: string;
    incoming_weight: number;
    sold_weight: number;
    carry_out_weight: number;
    wastage_weight: number;
    wastage_rate: number;
}

interface ProductWastage {
    product_id: number;
    product_name: string;
    incoming_weight: number;
    sold_weight: number;
    wastage_weight: number;
    wastage_rate: number;
}

interface WastageReport {
    period: { from: string | null; to: string | null };
    by_shipment: ShipmentWastage[];
    by_product: ProductWastage[];
    summary: {
        total_incoming: number;
        total_sold: number;
        total_wastage: number;
        overall_wastage_rate: number;
        shipments_count: number;
    };
}

export default function WastagePage() {
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    const queryString = params.toString();

    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'wastage', dateFrom, dateTo],
        queryFn: () => api.get<ApiResponse<WastageReport>>(
            `${endpoints.reports.inventoryWastage}${queryString ? `?${queryString}` : ''}`
        ),
    });
    const report = data?.data;

    const getWastageColor = (rate: number) => {
        if (rate <= 2) return 'text-green-600';
        if (rate <= 5) return 'text-yellow-600';
        if (rate <= 10) return 'text-orange-600';
        return 'text-red-600';
    };

    const getWastageBadge = (rate: number) => {
        if (rate <= 2) return <Badge className="bg-green-500">Excellent</Badge>;
        if (rate <= 5) return <Badge className="bg-yellow-500">Normal</Badge>;
        if (rate <= 10) return <Badge className="bg-orange-500">High</Badge>;
        return <Badge variant="destructive">Critical</Badge>;
    };

    const handleDownloadPdf = () => {
        const pdfParams = new URLSearchParams();
        if (dateFrom) pdfParams.append('date_from', dateFrom);
        if (dateTo) pdfParams.append('date_to', dateTo);
        window.open(`${process.env.NEXT_PUBLIC_API_URL}${endpoints.reports.inventoryWastagePdf}?${pdfParams}`, '_blank');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Wastage Report</h1>
                    <p className="text-muted-foreground">Weight loss analysis for settled shipments</p>
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
                                <CardTitle className="text-sm font-medium">Shipments</CardTitle>
                                <Truck className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.shipments_count}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Incoming</CardTitle>
                                <Scale className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {report.summary.total_incoming.toLocaleString()} kg
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Sold</CardTitle>
                                <Package className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-600">
                                    {report.summary.total_sold.toLocaleString()} kg
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-red-50 border-red-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-red-700">Total Wastage</CardTitle>
                                <AlertTriangle className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {report.summary.total_wastage.toLocaleString()} kg
                                </div>
                            </CardContent>
                        </Card>

                        <Card className={`${report.summary.overall_wastage_rate <= 5 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Wastage Rate</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${getWastageColor(report.summary.overall_wastage_rate)}`}>
                                    {report.summary.overall_wastage_rate.toFixed(1)}%
                                </div>
                                {getWastageBadge(report.summary.overall_wastage_rate)}
                            </CardContent>
                        </Card>
                    </div>

                    {/* By Shipment Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Wastage by Shipment</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.by_shipment.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Shipment</TableHead>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead className="text-right">Incoming</TableHead>
                                            <TableHead className="text-right">Sold</TableHead>
                                            <TableHead className="text-right">Wastage</TableHead>
                                            <TableHead className="text-center">Rate</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.by_shipment.map((shipment) => (
                                            <TableRow key={shipment.shipment_id}>
                                                <TableCell className="font-medium">{shipment.shipment_code}</TableCell>
                                                <TableCell>{shipment.supplier_name}</TableCell>
                                                <TableCell className="text-right">{shipment.incoming_weight.toLocaleString()} kg</TableCell>
                                                <TableCell className="text-right">{shipment.sold_weight.toLocaleString()} kg</TableCell>
                                                <TableCell className="text-right text-red-600 font-bold">
                                                    {shipment.wastage_weight.toLocaleString()} kg
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <span className={`font-bold ${getWastageColor(shipment.wastage_rate)}`}>
                                                        {shipment.wastage_rate.toFixed(1)}%
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No settled shipments in this period</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* By Product Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Wastage by Product</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.by_product.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-right">Incoming</TableHead>
                                            <TableHead className="text-right">Sold</TableHead>
                                            <TableHead className="text-right">Wastage</TableHead>
                                            <TableHead className="text-center">Rate</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.by_product.map((product) => (
                                            <TableRow key={product.product_id}>
                                                <TableCell className="font-medium">{product.product_name}</TableCell>
                                                <TableCell className="text-right">{product.incoming_weight.toLocaleString()} kg</TableCell>
                                                <TableCell className="text-right">{product.sold_weight.toLocaleString()} kg</TableCell>
                                                <TableCell className="text-right text-red-600">
                                                    {product.wastage_weight.toLocaleString()} kg
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <span className={`font-bold ${getWastageColor(product.wastage_rate)}`}>
                                                        {product.wastage_rate.toFixed(1)}%
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No product wastage data</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
