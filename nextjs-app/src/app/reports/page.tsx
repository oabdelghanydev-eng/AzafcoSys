'use client';

import Link from 'next/link';
import { toast } from 'sonner';
import {
    FileText,
    Users,
    Building2,
    Truck,
    Calendar,
    Download,
    DollarSign,
    TrendingUp,
    Package,
    Clock,
    BarChart3,
    Wallet,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const reportCategories = [
    {
        category: 'Daily Operations',
        icon: Calendar,
        reports: [
            {
                title: 'Daily Closing Report',
                description: 'Generate daily sales and collections summary',
                href: '/daily',
                available: true,
            },
        ],
    },
    {
        category: 'Financial Reports',
        icon: DollarSign,
        reports: [
            {
                title: 'Profit & Loss',
                description: 'Revenue, expenses, and net profit',
                href: '/reports/profit-loss',
                available: true,
            },
            {
                title: 'Cash Flow',
                description: 'Cash inflows and outflows',
                href: '/reports/cash-flow',
                available: true,
            },
        ],
    },
    {
        category: 'Sales Reports',
        icon: TrendingUp,
        reports: [
            {
                title: 'Sales by Product',
                description: 'Product-wise sales analysis',
                href: '/reports/sales/by-product',
                available: true,
            },
            {
                title: 'Sales by Customer',
                description: 'Customer-wise sales analysis',
                href: '/reports/sales/by-customer',
                available: true,
            },
        ],
    },
    {
        category: 'Customer Reports',
        icon: Users,
        reports: [
            {
                title: 'Customer Statement',
                description: 'Account statement for a customer',
                href: '/reports/customer',
                available: true,
            },
            {
                title: 'Customer Aging',
                description: 'Outstanding invoices by age',
                href: '/reports/customers/aging',
                available: true,
            },
            {
                title: 'Customer Balances',
                description: 'All customer balances summary',
                href: '/reports/customers/balances',
                available: true,
            },
        ],
    },
    {
        category: 'Inventory Reports',
        icon: Package,
        reports: [
            {
                title: 'Current Stock',
                description: 'Available inventory by product',
                href: '/reports/inventory/stock',
                available: true,
            },
            {
                title: 'Stock Movement',
                description: 'Inventory in/out movements',
                href: '/reports/inventory/movement',
                available: true,
            },
            {
                title: 'Wastage Report',
                description: 'Weight loss and wastage analysis',
                href: '/reports/inventory/wastage',
                available: true,
            },
        ],
    },
    {
        category: 'Supplier Reports',
        icon: Building2,
        reports: [
            {
                title: 'Supplier Statement',
                description: 'Account statement for a supplier',
                href: '/reports/supplier',
                available: true,
            },
            {
                title: 'Supplier Balances',
                description: 'All supplier balances summary',
                href: '/reports/suppliers/balances',
                available: true,
            },
            {
                title: 'Supplier Performance',
                description: 'Sales, wastage, settlement time',
                href: '/reports/suppliers/performance',
                available: true,
            },
            {
                title: 'Supplier Payments',
                description: 'Payments and expenses by supplier',
                href: '/reports/suppliers/payments',
                available: true,
            },
        ],
    },
    {
        category: 'Shipment Reports',
        icon: Truck,
        reports: [
            {
                title: 'Shipment Settlement',
                description: 'Shipment settlement report',
                href: '/shipments',
                available: true,
            },
        ],
    },
];

export default function ReportsPage() {
    return (
        <div className="space-y-8">
            <div>
                <h1 className="text-2xl font-bold">Reports</h1>
                <p className="text-muted-foreground">Generate and download reports</p>
            </div>

            {reportCategories.map((category) => {
                const CategoryIcon = category.icon;
                return (
                    <div key={category.category} className="space-y-4">
                        <div className="flex items-center gap-2">
                            <CategoryIcon className="h-5 w-5 text-primary" />
                            <h2 className="text-lg font-semibold">{category.category}</h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {category.reports.map((report) => (
                                <Link key={report.title} href={report.href}>
                                    <Card className="cursor-pointer hover:bg-muted/50 transition-colors h-full">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-base">{report.title}</CardTitle>
                                            <CardDescription className="text-sm">{report.description}</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Button variant="outline" size="sm" className="touch-target">
                                                <Download className="mr-2 h-4 w-4" />
                                                View Report
                                            </Button>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

