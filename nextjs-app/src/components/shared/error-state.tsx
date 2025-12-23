'use client';

import { AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ErrorStateProps {
    title?: string;
    message: string;
    code?: string;
    retry?: () => void;
}

export function ErrorState({
    title = 'Error',
    message,
    code,
    retry
}: ErrorStateProps) {
    return (
        <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
            <AlertCircle className="h-12 w-12 text-destructive mb-4" />
            <h3 className="text-lg font-semibold mb-1">{title}</h3>
            <p className="text-sm text-muted-foreground mb-2 max-w-sm">
                {message}
            </p>
            {code && (
                <code className="text-xs bg-muted px-2 py-1 rounded mb-4">
                    Code: {code}
                </code>
            )}
            {retry && (
                <Button variant="outline" onClick={retry} className="gap-2">
                    <RefreshCw className="h-4 w-4" />
                    Try Again
                </Button>
            )}
        </div>
    );
}
