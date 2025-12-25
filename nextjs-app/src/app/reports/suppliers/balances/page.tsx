'use client';

import { Download, Building2, TrendingUp, TrendingDown, Loader2 } from 'lucide-react';
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

interface SupplierBalance {
    supplier_id: number;
    supplier_code: string;
    supplier_name: string;
    balance: number;
    balance_type: 'we_owe_supplier' | 'supplier_owes_us' | 'settled';
}

interface SupplierBalancesReport {
    as_of_date: string;
    suppliers: SupplierBalance[];
    totals: {
        we_owe_suppliers: number;
        suppliers_owe_us: number;
        net_balance: number;
    };
    summary: {
        total_suppliers: number;
        suppliers_we_owe: number;
        suppliers_owe_us: number;
        settled_suppliers: number;
    };
}

export default function SupplierBalancesPage() {
    const { data, isLoading, error } = useQuery({
        queryKey: ['reports', 'supplier-balances'],
        queryFn: () => api.get<ApiResponse<SupplierBalancesReport>>(endpoints.reports.supplierBalances),
    });
    const report = data?.data;

    const getBalanceColor = (type: string) => {
        switch (type) {
            case 'we_owe_supplier': return 'text-red-600';
            case 'supplier_owes_us': return 'text-green-600';
            default: return 'text-gray-600';
        }
    };

    const getBalanceBadge = (type: string) => {
        switch (type) {
            case 'we_owe_supplier': return <Badge variant="destructive">We Owe</Badge>;
            case 'supplier_owes_us': return <Badge className="bg-green-500">They Owe</Badge>;
            default: return <Badge variant="secondary">Settled</Badge>;
        }
    };

    const { downloadPdf, isDownloading } = usePdfDownload();

    const handleDownloadPdf = () => {
        downloadPdf(endpoints.reports.supplierBalancesPdf, 'supplier-balances-report');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Supplier Balances</h1>
                    <p className="text-muted-foreground">All supplier balances summary</p>
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
                                <CardTitle className="text-sm font-medium">Total Suppliers</CardTitle>
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{report.summary.total_suppliers}</div>
                            </CardContent>
                        </Card>

                        <Card className="bg-red-50 border-red-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-red-700">We Owe Suppliers</CardTitle>
                                <TrendingDown className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {formatCurrency(report.totals.we_owe_suppliers)}
                                </div>
                                <p className="text-xs text-red-500">{report.summary.suppliers_we_owe} suppliers</p>
                            </CardContent>
                        </Card>

                        <Card className="bg-green-50 border-green-200">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">Suppliers Owe Us</CardTitle>
                                <TrendingUp className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.totals.suppliers_owe_us)}
                                </div>
                                <p className="text-xs text-green-500">{report.summary.suppliers_owe_us} suppliers</p>
                            </CardContent>
                        </Card>

                        <Card className={report.totals.net_balance >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Net Balance</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className={`text-2xl font-bold ${report.totals.net_balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(Math.abs(report.totals.net_balance))}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {report.totals.net_balance >= 0 ? 'Net receivable' : 'Net payable'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Suppliers Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {report.suppliers.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Balance</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.suppliers.map((supplier) => (
                                            <TableRow key={supplier.supplier_id}>
                                                <TableCell>
                                                    <div className="font-medium">{supplier.supplier_name}</div>
                                                    <div className="text-sm text-muted-foreground">{supplier.supplier_code}</div>
                                                </TableCell>
                                                <TableCell>{getBalanceBadge(supplier.balance_type)}</TableCell>
                                                <TableCell className={`text-right font-bold ${getBalanceColor(supplier.balance_type)}`}>
                                                    {formatCurrency(Math.abs(supplier.balance))}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-center text-muted-foreground py-8">No suppliers found</p>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
