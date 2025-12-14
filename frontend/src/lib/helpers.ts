// Helper to extract array data from API response
// API can return either T[] or { data: T[] }
export function extractData<T>(response: T[] | { data?: T[] } | undefined): T[] {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    return response.data || [];
}
