'use client';

import { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface StatCardProps {
    title: string;
    value: string | number;
    icon?: ReactNode;
    description?: string;
    trend?: {
        value: number;
        isPositive: boolean;
    };
    variant?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    className?: string;
}

const variantStyles = {
    default: {
        card: 'hover:shadow-lg hover:border-primary/20 transition-all duration-200',
        icon: 'bg-primary/10 text-primary',
        value: '',
    },
    success: {
        card: 'hover:shadow-lg hover:border-green-200 bg-gradient-to-br from-green-50 to-white transition-all duration-200',
        icon: 'bg-green-100 text-green-600',
        value: 'text-green-600',
    },
    warning: {
        card: 'hover:shadow-lg hover:border-orange-200 bg-gradient-to-br from-orange-50 to-white transition-all duration-200',
        icon: 'bg-orange-100 text-orange-600',
        value: 'text-orange-600',
    },
    danger: {
        card: 'hover:shadow-lg hover:border-red-200 bg-gradient-to-br from-red-50 to-white transition-all duration-200',
        icon: 'bg-red-100 text-red-600',
        value: 'text-red-600',
    },
    info: {
        card: 'hover:shadow-lg hover:border-blue-200 bg-gradient-to-br from-blue-50 to-white transition-all duration-200',
        icon: 'bg-blue-100 text-blue-600',
        value: 'text-blue-600',
    },
};

export function StatCard({
    title,
    value,
    icon,
    description,
    trend,
    variant = 'default',
    className
}: StatCardProps) {
    const styles = variantStyles[variant];

    return (
        <Card className={cn(styles.card, className)}>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium text-muted-foreground">
                            {title}
                        </p>
                        <p className={cn("text-2xl font-bold money", styles.value)}>
                            {value}
                        </p>
                        {description && (
                            <p className="text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                        {trend && (
                            <div className={cn(
                                "inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full",
                                trend.isPositive
                                    ? "bg-green-100 text-green-700"
                                    : "bg-red-100 text-red-700"
                            )}>
                                <span className={cn(
                                    "transition-transform duration-300",
                                    trend.isPositive ? "rotate-0" : "rotate-180"
                                )}>
                                    ↑
                                </span>
                                {Math.abs(trend.value)}%
                            </div>
                        )}
                    </div>
                    {icon && (
                        <div className={cn(
                            "p-3 rounded-xl transition-transform duration-200 hover:scale-110",
                            styles.icon
                        )}>
                            {icon}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

