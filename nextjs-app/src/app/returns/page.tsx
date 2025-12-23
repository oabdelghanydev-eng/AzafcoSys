'use client';

import Link from 'next/link';
import { Plus, Search, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { EmptyState } from '@/components/shared/empty-state';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useReturns } from '@/hooks/api/use-returns';
import type { Return } from '@/types/api';

function getStatusBadge(status: string) {
    const config: Record<string, { variant: 'default' | 'secondary' | 'destructive'; label: string }> = {
        completed: { variant: 'default', label: 'Completed' },
        pending: { variant: 'secondary', label: 'Pending' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
    };
    const c = config[status] || { variant: 'secondary', label: status };
    return <Badge variant={c.variant}>{c.label}</Badge>;
}

function ReturnCard({ returnItem }: { returnItem: Return }) {
    return (
        <Link href={`/returns/${returnItem.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{returnItem.return_number || `#${returnItem.id}`}</p>
                            <p className="text-sm text-muted-foreground">{returnItem.customer?.name}</p>
                        </div>
                        {getStatusBadge(returnItem.status)}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{formatDateShort(returnItem.date)}</span>
                        <span className="font-semibold text-orange-600 money">{formatMoney(returnItem.total)}</span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function ReturnsPage() {
    const { data: returns = [], isLoading, error, refetch } = useReturns();

    const isEmpty = !Array.isArray(returns) || returns.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading returns..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load returns"
                message="Could not fetch returns from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Returns</h1>
                    <p className="text-muted-foreground">Manage product returns</p>
                </div>
                <PermissionGate permission="returns.create">
                    <Button asChild className="touch-target">
                        <Link href="/returns/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Return
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search returns..." className="pl-10" />
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<RotateCcw className="h-12 w-12" />}
                    title="No returns found"
                    description="Record a new return to get started"
                    action={{ label: 'New Return', href: '/returns/new' }}
                />
            ) : (
                <>
                    <div className="grid gap-3 lg:hidden">
                        {Array.isArray(returns) && returns.map((r: Return) => (
                            <ReturnCard key={r.id} returnItem={r} />
                        ))}
                    </div>

                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Return #</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Array.isArray(returns) && returns.map((r: Return) => (
                                    <TableRow key={r.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>
                                            <Link href={`/returns/${r.id}`} className="font-medium hover:underline">
                                                {r.return_number || `#${r.id}`}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{r.customer?.name}</TableCell>
                                        <TableCell>{formatDateShort(r.date)}</TableCell>
                                        <TableCell className="text-right font-semibold text-orange-600 money">
                                            {formatMoney(r.total)}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(r.status)}</TableCell>
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
