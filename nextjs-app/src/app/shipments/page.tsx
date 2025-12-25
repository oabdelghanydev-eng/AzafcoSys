'use client';

import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Plus, Truck, Search } from 'lucide-react';
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
import { formatDateShort, formatInteger } from '@/lib/formatters';
import { useShipments } from '@/hooks/api/use-shipments';
import type { Shipment } from '@/types/api';

function getStatusBadge(status: string) {
    const config: Record<string, { variant: 'default' | 'secondary' | 'outline'; className: string }> = {
        open: { variant: 'default', className: 'bg-blue-500' },
        closed: { variant: 'secondary', className: 'bg-yellow-500 text-black' },
        settled: { variant: 'outline', className: 'bg-green-100 text-green-800 border-green-300' },
    };
    const c = config[status] || { variant: 'outline', className: '' };
    return <Badge variant={c.variant} className={c.className}>{status}</Badge>;
}

function ShipmentCard({ shipment }: { shipment: Shipment }) {
    return (
        <Link href={`/shipments/${shipment.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">Shipment #{shipment.id}</p>
                            <p className="text-sm text-muted-foreground">{shipment.supplier?.name}</p>
                        </div>
                        {getStatusBadge(shipment.status)}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{formatDateShort(shipment.date)}</span>
                        <span className="font-semibold">{formatInteger(shipment.total_cartons || 0)} cartons</span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function ShipmentsPage() {
    const router = useRouter();
    const { data, isLoading, error, refetch } = useShipments();

    const shipments = data?.data || [];
    const isEmpty = shipments.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading shipments..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load shipments"
                message="Could not fetch shipments from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Shipments</h1>
                    <p className="text-muted-foreground">Manage inventory shipments</p>
                </div>
                <PermissionGate permission="shipments.create">
                    <Button asChild className="touch-target">
                        <Link href="/shipments/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Shipment
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search shipments..." className="pl-10" />
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<Truck className="h-12 w-12" />}
                    title="No shipments found"
                    description="Record your first shipment"
                    action={{ label: 'New Shipment', href: '/shipments/new' }}
                />
            ) : (
                <>
                    <div className="grid gap-3 lg:hidden">
                        {shipments.map((s: Shipment) => <ShipmentCard key={s.id} shipment={s} />)}
                    </div>

                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Supplier</TableHead>
                                    <TableHead>Items</TableHead>
                                    <TableHead>Cartons</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {shipments.map((s: Shipment) => (
                                    <TableRow
                                        key={s.id}
                                        className="cursor-pointer hover:bg-muted/50"
                                        onClick={() => router.push(`/shipments/${s.id}`)}
                                    >
                                        <TableCell className="font-medium">#{s.id}</TableCell>
                                        <TableCell>{formatDateShort(s.date)}</TableCell>
                                        <TableCell>{s.supplier?.name}</TableCell>
                                        <TableCell>{s.items?.length || 0}</TableCell>
                                        <TableCell>{formatInteger(s.total_cartons || 0)}</TableCell>
                                        <TableCell>{getStatusBadge(s.status)}</TableCell>
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
