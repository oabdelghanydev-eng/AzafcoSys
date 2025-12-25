import { useState, useCallback } from 'react';
import { api } from '@/lib/api/client';

/**
 * Custom hook for secure PDF downloads with authentication
 * Uses the authenticated API client to fetch PDFs as blobs
 */
export function usePdfDownload() {
    const [isDownloading, setIsDownloading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const downloadPdf = useCallback(async (endpoint: string, filename: string) => {
        if (isDownloading) return;

        setIsDownloading(true);
        setError(null);

        try {
            await api.download(endpoint, `${filename}.pdf`);
        } catch (err) {
            console.error('PDF download failed:', err);
            setError('فشل تحميل ملف PDF');
        } finally {
            setIsDownloading(false);
        }
    }, [isDownloading]);

    return { downloadPdf, isDownloading, error };
}

