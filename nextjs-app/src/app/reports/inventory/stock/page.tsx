'use client';

import { Download, Package, Truck, Scale } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useInventoryStockReport } from '@/hooks/api/use-reports';
import { endpoints } from '@/lib/api/endpoints';

export default function InventoryStockPage() {
    const { data, isLoading, error } = useInventoryStockReport();
    const report = data?.data;

    const handleDownloadPdf = () => {
        window.open(`${process.env.NEXT_PUBLIC_API_URL}${endpoints.reports.inventoryStockPdf}`, '_blank');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Current Stock</h1>
                    <p className="text-muted-foreground">Available inventory by product</p>
                </div>
                <Button onClick={handleDownloadPdf} disabled={!report}>
                    <Download className="mr-2 h-4 w-4" />
                    Download PDF
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
                                <CardTitle className="text-sm font-medium">Products</CardTitle>
                                <Package className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_products}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Cartons</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_cartons.toLocaleString()}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Total Weight</CardTitle>
                                <Scale className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_weight.toLocaleString()} kg</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">Active Shipments</CardTitle>
                                <Truck className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.shipments_count}</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Products Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Stock by Product</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.products.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-right">Cartons</TableHead>
                                            <TableHead className="text-right">Weight (kg)</TableHead>
                                            <TableHead className="text-right">Shipments</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.products.map((product) => (
                                            <TableRow key={product.product_id}>
                                                <TableCell className="font-medium">{product.product_name}</TableCell>
                                                <TableCell className="text-right">{product.total_cartons.toLocaleString()}</TableCell>
                                                <TableCell className="text-right">{product.total_weight.toLocaleString()}</TableCell>
                                                <TableCell className="text-right">{product.shipments_count}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No stock available</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
