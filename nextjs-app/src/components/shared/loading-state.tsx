'use client';

import { Loader2 } from 'lucide-react';

interface LoadingStateProps {
    message?: string;
    fullScreen?: boolean;
}

export function LoadingState({
    message = 'Loading...',
    fullScreen = false
}: LoadingStateProps) {
    const content = (
        <div className="flex flex-col items-center justify-center gap-3 py-12">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
            <p className="text-sm text-muted-foreground">{message}</p>
        </div>
    );

    if (fullScreen) {
        return (
            <div className="fixed inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm z-50">
                {content}
            </div>
        );
    }

    return content;
}
