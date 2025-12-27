'use client';

import { useState, useMemo, useCallback, useEffect } from 'react';

interface UsePaginationOptions {
    initialPage?: number;
    perPage?: number;
}

interface PaginationMeta {
    currentPage: number;
    perPage: number;
    totalPages: number;
    total: number;
    hasMore: boolean;
}

interface UsePaginationReturn<T> {
    /** Current page data (client-side pagination) */
    pageData: T[];
    /** Pagination metadata */
    meta: PaginationMeta;
    /** Go to specific page */
    goToPage: (page: number) => void;
    /** Go to next page */
    nextPage: () => void;
    /** Go to previous page */
    prevPage: () => void;
    /** Set items per page */
    setPerPage: (perPage: number) => void;
    /** Query params for API calls */
    queryParams: { page: number; per_page: number };
}

/**
 * usePagination - Client-side pagination hook
 * 
 * For client-side pagination of already-loaded data.
 * For server-side pagination, use queryParams with your API.
 */
export function usePagination<T>(
    data: T[],
    options: UsePaginationOptions = {}
): UsePaginationReturn<T> {
    const { initialPage = 1, perPage: initialPerPage = 20 } = options;

    const [currentPage, setCurrentPage] = useState(initialPage);
    const [perPage, setPerPage] = useState(initialPerPage);

    const total = data.length;
    const totalPages = Math.ceil(total / perPage) || 1;

    // Clamp current page to valid range using useEffect (not during render)
    useEffect(() => {
        if (currentPage > totalPages) {
            setCurrentPage(totalPages);
        } else if (currentPage < 1) {
            setCurrentPage(1);
        }
    }, [currentPage, totalPages]);

    const validPage = Math.max(1, Math.min(currentPage, totalPages));

    const pageData = useMemo(() => {
        const start = (validPage - 1) * perPage;
        const end = start + perPage;
        return data.slice(start, end);
    }, [data, validPage, perPage]);

    const goToPage = useCallback((page: number) => {
        setCurrentPage(Math.max(1, Math.min(page, totalPages || 1)));
    }, [totalPages]);

    const nextPage = useCallback(() => {
        goToPage(currentPage + 1);
    }, [currentPage, goToPage]);

    const prevPage = useCallback(() => {
        goToPage(currentPage - 1);
    }, [currentPage, goToPage]);

    const handleSetPerPage = useCallback((newPerPage: number) => {
        setPerPage(newPerPage);
        setCurrentPage(1); // Reset to first page
    }, []);

    return {
        pageData,
        meta: {
            currentPage: validPage,
            perPage,
            totalPages,
            total,
            hasMore: validPage < totalPages,
        },
        goToPage,
        nextPage,
        prevPage,
        setPerPage: handleSetPerPage,
        queryParams: { page: validPage, per_page: perPage },
    };
}

/**
 * useServerPagination - For server-side paginated APIs
 * 
 * Use with React Query to handle pagination state.
 */
export function useServerPagination(options: UsePaginationOptions = {}) {
    const { initialPage = 1, perPage: initialPerPage = 20 } = options;

    const [currentPage, setCurrentPage] = useState(initialPage);
    const [perPage, setPerPageState] = useState(initialPerPage);

    const goToPage = useCallback((page: number) => {
        setCurrentPage(Math.max(1, page));
    }, []);

    const setPerPage = useCallback((newPerPage: number) => {
        setPerPageState(newPerPage);
        setCurrentPage(1);
    }, []);

    return {
        currentPage,
        perPage,
        goToPage,
        setPerPage,
        queryParams: { page: currentPage, per_page: perPage },
    };
}
