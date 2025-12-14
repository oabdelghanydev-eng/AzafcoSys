'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/hooks/useAuth';
import { api } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface DailyReport {
    date: string;
    sales: {
        count: number;
        total: number;
        discount: number;
    };
    collections: {
        count: number;
        total: number;
        cash: number;
        bank: number;
    };
    expenses: {
        count: number;
        total: number;
        cash: number;
        bank: number;
        supplier: number;
        company: number;
    };
    net: {
        cash: number;
        sales_vs_collections: number;
    };
}

export default function ReportsPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);

    const { data: reportResponse, isLoading, refetch } = useQuery({
        queryKey: ['daily-report', selectedDate],
        queryFn: () => api.getDailyReport(selectedDate),
        enabled: !!user && !!selectedDate,
    });

    if (authLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-xl">جاري التحميل...</div>
            </div>
        );
    }

    if (!user) {
        router.push('/login');
        return null;
    }

    const report = (reportResponse as { data: DailyReport })?.data;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 py-4">
                    <h1 className="text-2xl font-bold text-gray-900">التقارير</h1>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 py-8">
                {/* Date Selector */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>التقرير اليومي</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-end gap-4">
                            <div>
                                <Label>اختر التاريخ</Label>
                                <Input
                                    type="date"
                                    value={selectedDate}
                                    onChange={(e) => setSelectedDate(e.target.value)}
                                    className="w-48"
                                />
                            </div>
                            <Button onClick={() => refetch()}>
                                عرض التقرير
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {isLoading ? (
                    <div className="text-center py-8">جاري التحميل...</div>
                ) : report ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {/* Sales Card */}
                        <Card>
                            <CardHeader className="bg-blue-50">
                                <CardTitle className="text-blue-800">المبيعات</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">عدد الفواتير:</span>
                                        <span className="font-bold">{report.sales.count}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">الإجمالي:</span>
                                        <span className="font-bold text-blue-600">
                                            {report.sales.total.toLocaleString('ar-EG')} ج.م
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">الخصم:</span>
                                        <span className="font-bold text-red-600">
                                            {report.sales.discount.toLocaleString('ar-EG')} ج.م
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Collections Card */}
                        <Card>
                            <CardHeader className="bg-green-50">
                                <CardTitle className="text-green-800">التحصيلات</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">عدد الإيصالات:</span>
                                        <span className="font-bold">{report.collections.count}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">الإجمالي:</span>
                                        <span className="font-bold text-green-600">
                                            {report.collections.total.toLocaleString('ar-EG')} ج.م
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">نقدي:</span>
                                        <span>{report.collections.cash.toLocaleString('ar-EG')} ج.م</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">بنك:</span>
                                        <span>{report.collections.bank.toLocaleString('ar-EG')} ج.م</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Expenses Card */}
                        <Card>
                            <CardHeader className="bg-red-50">
                                <CardTitle className="text-red-800">المصروفات</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">العدد:</span>
                                        <span className="font-bold">{report.expenses.count}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">الإجمالي:</span>
                                        <span className="font-bold text-red-600">
                                            {report.expenses.total.toLocaleString('ar-EG')} ج.م
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">مصروفات موردين:</span>
                                        <span>{report.expenses.supplier.toLocaleString('ar-EG')} ج.م</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">مصروفات شركة:</span>
                                        <span>{report.expenses.company.toLocaleString('ar-EG')} ج.م</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Net Summary */}
                        <Card className="md:col-span-2 lg:col-span-3">
                            <CardHeader className="bg-slate-800 text-white">
                                <CardTitle>الملخص</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <div className="grid grid-cols-2 gap-8">
                                    <div className="text-center p-4 bg-green-50 rounded-lg">
                                        <div className="text-sm text-gray-600 mb-1">صافي النقدية</div>
                                        <div className={`text-2xl font-bold ${report.net.cash >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {report.net.cash.toLocaleString('ar-EG')} ج.م
                                        </div>
                                    </div>
                                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                                        <div className="text-sm text-gray-600 mb-1">فرق المبيعات والتحصيل</div>
                                        <div className={`text-2xl font-bold ${report.net.sales_vs_collections >= 0 ? 'text-blue-600' : 'text-green-600'}`}>
                                            {report.net.sales_vs_collections.toLocaleString('ar-EG')} ج.م
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <div className="text-center py-8 text-gray-500">
                        اختر تاريخ لعرض التقرير
                    </div>
                )}
            </main>
        </div>
    );
}
