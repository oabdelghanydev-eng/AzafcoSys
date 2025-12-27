'use client';

import * as React from 'react';
import { RefreshCw, Clock, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

interface DataFreshnessProps {
    /** When the data was last fetched (timestamp) */
    lastUpdated?: Date | number | null;
    /** Is data currently being fetched? */
    isLoading?: boolean;
    /** Is data stale (background refetch happening)? */
    isStale?: boolean;
    /** Is there an error? */
    isError?: boolean;
    /** Manual refresh trigger */
    onRefresh?: () => void;
    /** Stale threshold in milliseconds (default: 30 seconds) */
    staleThreshold?: number;
    className?: string;
}

/**
 * DataFreshness - Visual indicator for data staleness
 * 
 * Shows when data was last updated and provides refresh button.
 * Useful for dashboards and lists that need real-time accuracy.
 */
export function DataFreshness({
    lastUpdated,
    isLoading = false,
    isStale = false,
    isError = false,
    onRefresh,
    staleThreshold = 30000, // 30 seconds
    className,
}: DataFreshnessProps) {
    const [now, setNow] = React.useState(Date.now());

    // Update "now" every second to keep freshness indicator accurate
    React.useEffect(() => {
        const interval = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(interval);
    }, []);

    const lastUpdatedTime = lastUpdated instanceof Date
        ? lastUpdated.getTime()
        : lastUpdated ?? 0;

    const age = now - lastUpdatedTime;
    const isDataStale = isStale || age > staleThreshold;

    const formatAge = (ms: number) => {
        const seconds = Math.floor(ms / 1000);
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        return `${hours}h ago`;
    };

    return (
        <div
            className={cn(
                'flex items-center gap-2 text-xs',
                isError ? 'text-destructive' : isDataStale ? 'text-orange-600' : 'text-muted-foreground',
                className
            )}
        >
            {isError ? (
                <>
                    <AlertTriangle className="h-3 w-3" />
                    <span>Error loading data</span>
                </>
            ) : isLoading ? (
                <>
                    <RefreshCw className="h-3 w-3 animate-spin" />
                    <span>Updating...</span>
                </>
            ) : lastUpdatedTime ? (
                <>
                    <Clock className="h-3 w-3" />
                    <span>{formatAge(age)}</span>
                    {isDataStale && <span className="text-orange-600">(stale)</span>}
                </>
            ) : null}

            {onRefresh && !isLoading && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-5 w-5"
                    onClick={onRefresh}
                >
                    <RefreshCw className="h-3 w-3" />
                    <span className="sr-only">Refresh</span>
                </Button>
            )}
        </div>
    );
}

/**
 * StaleOverlay - Overlay for stale data indication
 * 
 * Shows a semi-transparent overlay when data is being updated.
 */
interface StaleOverlayProps {
    isLoading?: boolean;
    isStale?: boolean;
    children: React.ReactNode;
    className?: string;
}

export function StaleOverlay({
    isLoading = false,
    isStale = false,
    children,
    className,
}: StaleOverlayProps) {
    const showOverlay = isLoading && isStale;

    return (
        <div className={cn('relative', className)}>
            {children}
            {showOverlay && (
                <div className="absolute inset-0 bg-background/50 flex items-center justify-center rounded-lg">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <RefreshCw className="h-4 w-4 animate-spin" />
                        Updating...
                    </div>
                </div>
            )}
        </div>
    );
}
