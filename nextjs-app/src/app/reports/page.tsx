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
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const reports = [
    {
        title: 'Daily Closing Report',
        description: 'Generate daily sales and collections summary',
        icon: Calendar,
        href: '/daily',
        available: true,
    },
    {
        title: 'Customer Statement',
        description: 'Account statement for a customer',
        icon: Users,
        href: '/reports/customer',
        available: true,
    },
    {
        title: 'Supplier Statement',
        description: 'Account statement for a supplier',
        icon: Building2,
        href: '/reports/supplier',
        available: true,
    },
    {
        title: 'Shipment Settlement',
        description: 'Shipment settlement report',
        icon: Truck,
        href: '/reports/shipment',
        available: false,
    },
    {
        title: 'Sales Report',
        description: 'Detailed sales analysis',
        icon: FileText,
        href: '/reports/sales',
        available: false,
    },
];

export default function ReportsPage() {
    const handleNotAvailable = () => {
        toast.info('Coming soon!');
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold">Reports</h1>
                <p className="text-muted-foreground">Generate and download reports</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((report) => {
                    const Icon = report.icon;

                    if (!report.available) {
                        return (
                            <Card
                                key={report.title}
                                className="cursor-pointer hover:bg-muted/50 transition-colors opacity-60"
                                onClick={handleNotAvailable}
                            >
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 rounded-lg bg-muted">
                                            <Icon className="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">{report.title}</CardTitle>
                                            <CardDescription className="text-sm">{report.description}</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">Coming soon</p>
                                </CardContent>
                            </Card>
                        );
                    }

                    return (
                        <Link key={report.title} href={report.href}>
                            <Card className="cursor-pointer hover:bg-muted/50 transition-colors h-full">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 rounded-lg bg-primary/10">
                                            <Icon className="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">{report.title}</CardTitle>
                                            <CardDescription className="text-sm">{report.description}</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <Button variant="outline" size="sm" className="touch-target">
                                        <Download className="mr-2 h-4 w-4" />
                                        Generate
                                    </Button>
                                </CardContent>
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
