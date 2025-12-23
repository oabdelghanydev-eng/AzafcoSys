'use client';

import Link from 'next/link';
import { Plus, Search, Users } from 'lucide-react';
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
import { formatMoney } from '@/lib/formatters';
import { useCustomers } from '@/hooks/api/use-customers';
import type { Customer } from '@/types/api';

function CustomerCard({ customer }: { customer: Customer }) {
    return (
        <Link href={`/customers/${customer.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{customer.name}</p>
                            <p className="text-sm text-muted-foreground">{customer.customer_code}</p>
                        </div>
                        <Badge variant={customer.is_active ? 'default' : 'secondary'}>
                            {customer.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{customer.phone || '-'}</span>
                        <span className={`font-semibold money ${customer.balance > 0 ? 'text-orange-600' : customer.balance < 0 ? 'text-green-600' : ''}`}>
                            {formatMoney(customer.balance)}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function CustomersPage() {
    const { data, isLoading, error, refetch } = useCustomers();

    const customers = data?.data || [];
    const isEmpty = customers.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading customers..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load customers"
                message="Could not fetch customers from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Customers</h1>
                    <p className="text-muted-foreground">Manage customers</p>
                </div>
                <PermissionGate permission="customers.create">
                    <Button asChild className="touch-target">
                        <Link href="/customers/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Customer
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search customers..." className="pl-10" />
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<Users className="h-12 w-12" />}
                    title="No customers found"
                    description="Add your first customer"
                    action={{ label: 'New Customer', href: '/customers/new' }}
                />
            ) : (
                <>
                    {/* Mobile */}
                    <div className="grid gap-3 lg:hidden">
                        {customers.map((c: Customer) => <CustomerCard key={c.id} customer={c} />)}
                    </div>

                    {/* Desktop */}
                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead className="text-right">Balance</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.map((c: Customer) => (
                                    <TableRow key={c.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>{c.customer_code}</TableCell>
                                        <TableCell>
                                            <Link href={`/customers/${c.id}`} className="font-medium hover:underline">
                                                {c.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{c.phone || '-'}</TableCell>
                                        <TableCell className={`text-right font-semibold money ${c.balance > 0 ? 'text-orange-600' : c.balance < 0 ? 'text-green-600' : ''}`}>
                                            {formatMoney(c.balance)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={c.is_active ? 'default' : 'secondary'}>
                                                {c.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
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
