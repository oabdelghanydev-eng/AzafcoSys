// Environment variables
export const config = {
    apiUrl: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api',
    appName: 'Sales Management System',
    currency: {
        symbol: 'QAR',
        code: 'QAR',
        locale: 'en-QA',
    },
    defaultLocale: 'en',
    locales: ['en', 'ar'] as const,
} as const;

export type Locale = (typeof config.locales)[number];
