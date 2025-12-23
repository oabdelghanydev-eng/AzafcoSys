'use client';

import Link from 'next/link';
import { Plus, Search, Building2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { EmptyState } from '@/components/shared/empty-state';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney } from '@/lib/formatters';
import { useSuppliers } from '@/hooks/api/use-suppliers';
import type { Supplier } from '@/types/api';

function SupplierCard({ supplier }: { supplier: Supplier }) {
    return (
        <Link href={`/suppliers/${supplier.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{supplier.name}</p>
                            <p className="text-sm text-muted-foreground">{supplier.supplier_code}</p>
                        </div>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{supplier.phone || '-'}</span>
                        <span className={`font-semibold money ${supplier.balance > 0 ? 'text-red-600' : ''}`}>
                            {formatMoney(supplier.balance)}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function SuppliersPage() {
    const { data, isLoading, error, refetch } = useSuppliers();

    const suppliers = data?.data || [];
    const isEmpty = suppliers.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading suppliers..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load suppliers"
                message="Could not fetch suppliers from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Suppliers</h1>
                    <p className="text-muted-foreground">Manage suppliers</p>
                </div>
                <PermissionGate permission="suppliers.create">
                    <Button asChild className="touch-target">
                        <Link href="/suppliers/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Supplier
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search suppliers..." className="pl-10" />
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<Building2 className="h-12 w-12" />}
                    title="No suppliers found"
                    description="Add your first supplier"
                    action={{ label: 'New Supplier', href: '/suppliers/new' }}
                />
            ) : (
                <>
                    <div className="grid gap-3 lg:hidden">
                        {suppliers.map((s: Supplier) => <SupplierCard key={s.id} supplier={s} />)}
                    </div>

                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead className="text-right">Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {suppliers.map((s: Supplier) => (
                                    <TableRow key={s.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>{s.supplier_code}</TableCell>
                                        <TableCell>
                                            <Link href={`/suppliers/${s.id}`} className="font-medium hover:underline">
                                                {s.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{s.phone || '-'}</TableCell>
                                        <TableCell className={`text-right font-semibold money ${s.balance > 0 ? 'text-red-600' : ''}`}>
                                            {formatMoney(s.balance)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </>
            )}
        </div>
    );
}
