'use client';

import * as React from 'react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface PaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    disabled?: boolean;
    className?: string;
    /** Number of page buttons to show (default: 5) */
    maxPageButtons?: number;
}

/**
 * Pagination - Page navigation for lists
 * 
 * Shows first/prev/page numbers/next/last buttons.
 * Automatically adjusts visible page range.
 */
export function Pagination({
    currentPage,
    totalPages,
    onPageChange,
    disabled = false,
    className,
    maxPageButtons = 5,
}: PaginationProps) {
    if (totalPages <= 1) return null;

    // Calculate page range to show
    const range = React.useMemo(() => {
        const half = Math.floor(maxPageButtons / 2);
        let start = Math.max(1, currentPage - half);
        const end = Math.min(totalPages, start + maxPageButtons - 1);

        // Adjust start if we're near the end
        if (end - start < maxPageButtons - 1) {
            start = Math.max(1, end - maxPageButtons + 1);
        }

        const pages: number[] = [];
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    }, [currentPage, totalPages, maxPageButtons]);

    const canGoFirst = currentPage > 1;
    const canGoBack = currentPage > 1;
    const canGoForward = currentPage < totalPages;
    const canGoLast = currentPage < totalPages;

    return (
        <nav
            role="navigation"
            aria-label="Pagination"
            className={cn('flex items-center justify-center gap-1', className)}
        >
            {/* First */}
            <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                onClick={() => onPageChange(1)}
                disabled={disabled || !canGoFirst}
            >
                <ChevronsLeft className="h-4 w-4" />
                <span className="sr-only">First page</span>
            </Button>

            {/* Previous */}
            <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                onClick={() => onPageChange(currentPage - 1)}
                disabled={disabled || !canGoBack}
            >
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Previous page</span>
            </Button>

            {/* Page numbers */}
            {range[0] > 1 && (
                <span className="px-2 text-sm text-muted-foreground">...</span>
            )}

            {range.map((page) => (
                <Button
                    key={page}
                    variant={page === currentPage ? 'default' : 'outline'}
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => onPageChange(page)}
                    disabled={disabled}
                >
                    {page}
                </Button>
            ))}

            {range[range.length - 1] < totalPages && (
                <span className="px-2 text-sm text-muted-foreground">...</span>
            )}

            {/* Next */}
            <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                onClick={() => onPageChange(currentPage + 1)}
                disabled={disabled || !canGoForward}
            >
                <ChevronRight className="h-4 w-4" />
                <span className="sr-only">Next page</span>
            </Button>

            {/* Last */}
            <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                onClick={() => onPageChange(totalPages)}
                disabled={disabled || !canGoLast}
            >
                <ChevronsRight className="h-4 w-4" />
                <span className="sr-only">Last page</span>
            </Button>
        </nav>
    );
}

// Info component for showing "X - Y of Z"
interface PaginationInfoProps {
    currentPage: number;
    perPage: number;
    total: number;
    className?: string;
}

export function PaginationInfo({
    currentPage,
    perPage,
    total,
    className,
}: PaginationInfoProps) {
    const start = (currentPage - 1) * perPage + 1;
    const end = Math.min(currentPage * perPage, total);

    return (
        <p className={cn('text-sm text-muted-foreground', className)}>
            Showing {start}-{end} of {total}
        </p>
    );
}
