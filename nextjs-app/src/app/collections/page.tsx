'use client';

import Link from 'next/link';
import { Plus, Search, Receipt, Filter } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { EmptyState } from '@/components/shared/empty-state';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useCollections } from '@/hooks/api/use-collections';
import type { Collection } from '@/types/api';

function getPaymentMethodBadge(method: string) {
    const styles: Record<string, string> = {
        cash: 'bg-green-100 text-green-800',
        bank: 'bg-blue-100 text-blue-800',
        bank_transfer: 'bg-blue-100 text-blue-800',
    };
    return (
        <Badge variant="outline" className={styles[method] || ''}>
            {method === 'bank' || method === 'bank_transfer' ? 'Bank' : 'Cash'}
        </Badge>
    );
}

function CollectionCard({ collection }: { collection: Collection }) {
    return (
        <Link href={`/collections/${collection.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{collection.receipt_number}</p>
                            <p className="text-sm text-muted-foreground">{collection.customer?.name}</p>
                        </div>
                        {getPaymentMethodBadge(collection.payment_method)}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{formatDateShort(collection.date)}</span>
                        <span className="font-semibold text-green-600 money">{formatMoney(collection.amount)}</span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function CollectionsPage() {
    const { data, isLoading, error, refetch } = useCollections();

    const collections = data?.data || [];
    const isEmpty = collections.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading collections..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load collections"
                message="Could not fetch collections from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Collections</h1>
                    <p className="text-muted-foreground">Manage customer payments</p>
                </div>
                <PermissionGate permission="collections.create">
                    <Button asChild className="touch-target">
                        <Link href="/collections/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Collection
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            {/* Filters */}
            <div className="flex flex-col sm:flex-row gap-3">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input placeholder="Search collections..." className="pl-10" />
                </div>
                <Button variant="outline" className="touch-target">
                    <Filter className="mr-2 h-4 w-4" />
                    Filters
                </Button>
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<Receipt className="h-12 w-12" />}
                    title="No collections found"
                    description="Record a new collection to get started"
                    action={{ label: 'New Collection', href: '/collections/new' }}
                />
            ) : (
                <>
                    {/* Mobile */}
                    <div className="grid gap-3 lg:hidden">
                        {collections.map((c: Collection) => (
                            <CollectionCard key={c.id} collection={c} />
                        ))}
                    </div>

                    {/* Desktop */}
                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Receipt #</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Payment</TableHead>
                                    <TableHead>Distribution</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {collections.map((c: Collection) => (
                                    <TableRow key={c.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>
                                            <Link href={`/collections/${c.id}`} className="font-medium hover:underline">
                                                {c.receipt_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{c.customer?.name}</TableCell>
                                        <TableCell>{formatDateShort(c.date)}</TableCell>
                                        <TableCell className="text-right font-semibold text-green-600 money">
                                            {formatMoney(c.amount)}
                                        </TableCell>
                                        <TableCell>{getPaymentMethodBadge(c.payment_method)}</TableCell>
                                        <TableCell className="capitalize">{c.distribution_method}</TableCell>
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
