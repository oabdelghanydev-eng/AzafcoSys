'use client';

import Link from 'next/link';
import { AlertCircle, Calendar, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { LoadingState } from '@/components/shared/loading-state';
import { useCurrentDay } from '@/hooks/api/use-daily-report';

interface RequireOpenDayProps {
    children: React.ReactNode;
}

/**
 * Wrapper component that requires an open daily report to access content.
 * If no daily report is open, shows a message directing user to open one.
 */
export function RequireOpenDay({ children }: RequireOpenDayProps) {
    const { data: currentDay, isLoading, error: _error } = useCurrentDay();

    if (isLoading) {
        return <LoadingState message="Checking daily report..." />;
    }

    // Check if there's an open daily report
    const hasOpenDay = currentDay && currentDay.status === 'open';

    if (!hasOpenDay) {
        return (
            <div className="flex items-center justify-center min-h-[60vh]">
                <Card className="max-w-md w-full">
                    <CardHeader className="text-center">
                        <div className="mx-auto h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                            <AlertCircle className="h-8 w-8 text-amber-600" />
                        </div>
                        <CardTitle>No Open Daily Report</CardTitle>
                        <CardDescription>
                            You need to open a daily report before you can create transactions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground text-center">
                            Daily reports track all financial transactions for a specific day.
                            Please open today&apos;s report first.
                        </p>
                        <Link href="/daily" className="block">
                            <Button className="w-full" size="lg">
                                <Calendar className="h-4 w-4 mr-2" />
                                Go to Daily Reports
                                <ArrowRight className="h-4 w-4 ml-2" />
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return <>{children}</>;
}
