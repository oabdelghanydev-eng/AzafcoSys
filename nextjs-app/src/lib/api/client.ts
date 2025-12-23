import { config } from '@/lib/config';

interface ApiError {
    message: string;
    code?: string;
    errors?: Record<string, string[]>;
}

// Helper to get token from localStorage
function getAuthToken(): string | null {
    if (typeof window === 'undefined') return null;
    try {
        const storage = localStorage.getItem('auth-storage');
        if (storage) {
            const parsed = JSON.parse(storage);
            return parsed?.state?.token || null;
        }
    } catch {
        return null;
    }
    return null;
}

class ApiClient {
    private baseUrl: string;
    private csrfInitialized = false;

    constructor() {
        this.baseUrl = config.apiUrl;
    }

    private async initCsrf(): Promise<void> {
        if (this.csrfInitialized) return;

        const csrfUrl = this.baseUrl.replace('/api', '/sanctum/csrf-cookie');
        await fetch(csrfUrl, {
            credentials: 'include',
        });
        this.csrfInitialized = true;
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        await this.initCsrf();

        const url = `${this.baseUrl}${endpoint}`;
        const token = getAuthToken();

        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
            ...options.headers,
        };

        const response = await fetch(url, {
            ...options,
            headers,
            credentials: 'include',
        });

        if (!response.ok) {
            const error: ApiError = await response.json().catch(() => ({
                message: 'An unexpected error occurred',
            }));

            // Handle 401 - Unauthorized (only redirect if not already on login page)
            if (response.status === 401) {
                if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
                    window.location.href = '/login';
                }
                throw new Error('Session expired');
            }

            throw error;
        }

        // Handle 204 No Content
        if (response.status === 204) {
            return {} as T;
        }

        return response.json();
    }

    async get<T>(endpoint: string): Promise<T> {
        return this.request<T>(endpoint, { method: 'GET' });
    }

    async post<T>(endpoint: string, data?: unknown): Promise<T> {
        return this.request<T>(endpoint, {
            method: 'POST',
            body: data ? JSON.stringify(data) : undefined,
        });
    }

    async put<T>(endpoint: string, data: unknown): Promise<T> {
        return this.request<T>(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async delete<T>(endpoint: string): Promise<T> {
        return this.request<T>(endpoint, { method: 'DELETE' });
    }

    // For file downloads (PDF)
    async download(endpoint: string, filename: string): Promise<void> {
        await this.initCsrf();
        const token = getAuthToken();

        const headers: HeadersInit = token
            ? { 'Authorization': `Bearer ${token}` }
            : {};

        const response = await fetch(`${this.baseUrl}${endpoint}`, {
            credentials: 'include',
            headers,
        });

        if (!response.ok) {
            throw new Error('Download failed');
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(blobUrl);
        document.body.removeChild(a);
    }
}

export const api = new ApiClient();
export type { ApiError };
