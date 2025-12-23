'use client';

import { useState, useMemo, useCallback } from 'react';

/**
 * Custom hook for client-side search/filtering
 * 
 * @param items - Array of items to filter
 * @param searchFields - Array of field names to search in
 * @returns Object with query, setQuery, and filtered items
 * 
 * @example
 * const { query, setQuery, filtered } = useSearch(customers, ['name', 'phone', 'customer_code']);
 * 
 * <Input value={query} onChange={(e) => setQuery(e.target.value)} />
 * {filtered.map(customer => ...)}
 */
export function useSearch<T extends Record<string, unknown>>(
    items: T[],
    searchFields: (keyof T)[]
) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        if (!query.trim()) return items;

        const lowerQuery = query.toLowerCase().trim();

        return items.filter(item =>
            searchFields.some(field => {
                const value = item[field];
                if (value === null || value === undefined) return false;
                return String(value).toLowerCase().includes(lowerQuery);
            })
        );
    }, [items, query, searchFields]);

    const clearSearch = useCallback(() => {
        setQuery('');
    }, []);

    return {
        query,
        setQuery,
        filtered,
        clearSearch,
        hasQuery: query.trim().length > 0,
        resultCount: filtered.length,
        totalCount: items.length,
    };
}

/**
 * Hook for debounced search (useful for API calls)
 */
export function useDebouncedSearch<T extends Record<string, unknown>>(
    items: T[],
    searchFields: (keyof T)[],
    debounceMs: number = 300
) {
    const [query, setQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');

    // Debounce the query
    useMemo(() => {
        const timer = setTimeout(() => {
            setDebouncedQuery(query);
        }, debounceMs);
        return () => clearTimeout(timer);
    }, [query, debounceMs]);

    const filtered = useMemo(() => {
        if (!debouncedQuery.trim()) return items;

        const lowerQuery = debouncedQuery.toLowerCase().trim();

        return items.filter(item =>
            searchFields.some(field => {
                const value = item[field];
                if (value === null || value === undefined) return false;
                return String(value).toLowerCase().includes(lowerQuery);
            })
        );
    }, [items, debouncedQuery, searchFields]);

    return {
        query,
        setQuery,
        filtered,
        isSearching: query !== debouncedQuery,
    };
}
