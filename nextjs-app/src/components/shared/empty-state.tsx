'use client';

import { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import Link from 'next/link';

interface EmptyStateProps {
    icon: ReactNode;
    title: string;
    description?: string;
    action?: {
        label: string;
        href?: string;
        onClick?: () => void;
    };
}

export function EmptyState({
    icon,
    title,
    description,
    action
}: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
            <div className="text-muted-foreground mb-4">
                {icon}
            </div>
            <h3 className="text-lg font-semibold mb-1">{title}</h3>
            {description && (
                <p className="text-sm text-muted-foreground mb-4 max-w-sm">
                    {description}
                </p>
            )}
            {action && (
                action.href ? (
                    <Button asChild>
                        <Link href={action.href}>{action.label}</Link>
                    </Button>
                ) : (
                    <Button onClick={action.onClick}>{action.label}</Button>
                )
            )}
        </div>
    );
}
