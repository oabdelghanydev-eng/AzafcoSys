'use client';

import { cn } from '@/lib/utils';

interface SkeletonProps {
    className?: string;
}

export function Skeleton({ className }: SkeletonProps) {
    return (
        <div className={cn("skeleton h-4 w-full", className)} />
    );
}

interface SkeletonCardProps {
    className?: string;
}

export function SkeletonCard({ className }: SkeletonCardProps) {
    return (
        <div className={cn("p-6 border rounded-lg bg-card space-y-4", className)}>
            <div className="flex items-start justify-between">
                <div className="space-y-3 flex-1">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-8 w-32" />
                    <Skeleton className="h-3 w-20" />
                </div>
                <Skeleton className="h-12 w-12 rounded-xl" />
            </div>
        </div>
    );
}

interface SkeletonTableProps {
    rows?: number;
    columns?: number;
    className?: string;
}

export function SkeletonTable({ rows = 5, columns = 4, className }: SkeletonTableProps) {
    return (
        <div className={cn("border rounded-lg overflow-hidden", className)}>
            {/* Header */}
            <div className="bg-muted px-4 py-3 flex gap-4">
                {Array.from({ length: columns }).map((_, i) => (
                    <Skeleton key={`header-${i}`} className="h-4 flex-1" />
                ))}
            </div>
            {/* Rows */}
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <div
                    key={`row-${rowIndex}`}
                    className="px-4 py-4 flex gap-4 border-t"
                >
                    {Array.from({ length: columns }).map((_, colIndex) => (
                        <Skeleton
                            key={`cell-${rowIndex}-${colIndex}`}
                            className={cn(
                                "h-4 flex-1",
                                colIndex === 0 && "w-40 flex-none"
                            )}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}

interface SkeletonListProps {
    items?: number;
    className?: string;
}

export function SkeletonList({ items = 5, className }: SkeletonListProps) {
    return (
        <div className={cn("space-y-3", className)}>
            {Array.from({ length: items }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-4 p-4 border rounded-lg"
                >
                    <Skeleton className="h-10 w-10 rounded-full flex-none" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                    <Skeleton className="h-6 w-16 rounded-full flex-none" />
                </div>
            ))}
        </div>
    );
}
