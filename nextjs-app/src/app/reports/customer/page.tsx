'use client';

import { User, FileText } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';
import { useCustomers } from '@/hooks/api/use-customers';
import Link from 'next/link';

export default function CustomerStatementSelectPage() {
    const { data, isLoading } = useCustomers();
    const customers = data?.data || [];

    return (
        <div className="p-6 space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <div className="p-3 bg-primary/10 rounded-lg">
                    <User className="h-6 w-6 text-primary" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold">Customer Statement</h1>
                    <p className="text-muted-foreground">Select a customer to view their account statement</p>
                </div>
            </div>

            {/* Info */}
            <Card>
                <CardContent className="pt-6">
                    <p className="text-muted-foreground">
                        Select a customer from the list below to view their account statement
                    </p>
                </CardContent>
            </Card>

            {/* Customers List */}
            <Card>
                <CardHeader>
                    <CardTitle>Select Customer</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="text-center py-8 text-muted-foreground">
                            Loading...
                        </div>
                    ) : customers.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            No customers found
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead className="text-left">Balance</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.map((customer) => (
                                    <TableRow key={customer.id}>
                                        <TableCell className="font-medium">{customer.customer_code}</TableCell>
                                        <TableCell>{customer.name}</TableCell>
                                        <TableCell>{customer.phone || '-'}</TableCell>
                                        <TableCell className={`text-left money ${customer.balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                            {formatCurrency(customer.balance)}
                                        </TableCell>
                                        <TableCell>
                                            <Link href={`/reports/customer/${customer.id}`}>
                                                <Button size="sm" variant="outline">
                                                    <FileText className="h-4 w-4 mr-2" />
                                                    View Statement
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
