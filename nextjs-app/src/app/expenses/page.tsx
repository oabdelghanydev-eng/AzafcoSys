'use client';

import Link from 'next/link';
import { Plus, Search, Wallet, Filter } from 'lucide-react';
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
import { useExpenses } from '@/hooks/api/use-expenses';
import type { Expense } from '@/types/api';

function getTypeBadge(type: string) {
    const styles: Record<string, string> = {
        company: 'bg-purple-100 text-purple-800',
        supplier: 'bg-blue-100 text-blue-800',
        supplier_payment: 'bg-orange-100 text-orange-800',
    };
    return <Badge variant="outline" className={styles[type] || ''}>{type.replace('_', ' ')}</Badge>;
}

function ExpenseCard({ expense }: { expense: Expense }) {
    return (
        <Link href={`/expenses/${expense.id}`}>
            <Card className="hover:bg-muted/50 transition-colors">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between mb-2">
                        <div>
                            <p className="font-semibold">{expense.description}</p>
                            <p className="text-sm text-muted-foreground">
                                {expense.supplier?.name || 'Company'}
                            </p>
                        </div>
                        {getTypeBadge(expense.type)}
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{formatDateShort(expense.date)}</span>
                        <span className="font-semibold text-red-600 money">{formatMoney(expense.amount)}</span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function ExpensesPage() {
    const { data, isLoading, error, refetch } = useExpenses();

    const expenses = data?.data || [];
    const isEmpty = expenses.length === 0;

    if (isLoading) {
        return <LoadingState message="Loading expenses..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load expenses"
                message="Could not fetch expenses from server"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Expenses</h1>
                    <p className="text-muted-foreground">Manage expenses</p>
                </div>
                <PermissionGate permission="expenses.create">
                    <Button asChild className="touch-target">
                        <Link href="/expenses/new">
                            <Plus className="mr-2 h-4 w-4" />
                            New Expense
                        </Link>
                    </Button>
                </PermissionGate>
            </div>

            <div className="flex flex-col sm:flex-row gap-3">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input placeholder="Search expenses..." className="pl-10" />
                </div>
                <Button variant="outline" className="touch-target">
                    <Filter className="mr-2 h-4 w-4" />
                    Type
                </Button>
            </div>

            {isEmpty ? (
                <EmptyState
                    icon={<Wallet className="h-12 w-12" />}
                    title="No expenses found"
                    description="Record your first expense"
                    action={{ label: 'New Expense', href: '/expenses/new' }}
                />
            ) : (
                <>
                    <div className="grid gap-3 lg:hidden">
                        {expenses.map((e: Expense) => <ExpenseCard key={e.id} expense={e} />)}
                    </div>

                    <div className="hidden lg:block rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Payment</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.map((e: Expense) => (
                                    <TableRow key={e.id} className="cursor-pointer hover:bg-muted/50">
                                        <TableCell>{formatDateShort(e.date)}</TableCell>
                                        <TableCell>
                                            <Link href={`/expenses/${e.id}`} className="font-medium hover:underline">
                                                {e.description}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{getTypeBadge(e.type)}</TableCell>
                                        <TableCell className="text-right font-semibold text-red-600 money">
                                            {formatMoney(e.amount)}
                                        </TableCell>
                                        <TableCell className="capitalize">{e.payment_method?.replace('_', ' ')}</TableCell>
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
