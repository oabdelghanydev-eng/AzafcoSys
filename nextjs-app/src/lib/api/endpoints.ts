// API Endpoints - All backend routes
export const endpoints = {
    // Auth
    auth: {
        login: '/auth/login',
        logout: '/auth/logout',
        me: '/auth/me',
        googleRedirect: '/auth/google/redirect',
    },

    // Dashboard
    dashboard: {
        stats: '/dashboard',
        activity: '/dashboard/activity',
    },

    // Daily Report
    daily: {
        current: '/daily/current',
        available: '/daily/available',
        open: '/daily/open',
        close: '/daily/close',
    },

    // Alias for consistency
    dailyReports: {
        current: '/daily/current',
        availableDates: '/daily/available',
        open: '/daily/open',
        close: '/daily/close',
    },

    // Invoices
    invoices: {
        list: '/invoices',
        create: '/invoices',
        detail: (id: number) => `/invoices/${id}`,
        cancel: (id: number) => `/invoices/${id}/cancel`,
    },

    // Collections
    collections: {
        list: '/collections',
        create: '/collections',
        detail: (id: number) => `/collections/${id}`,
        cancel: (id: number) => `/collections/${id}/cancel`,
        unpaidInvoices: '/collections/unpaid-invoices',
    },

    // Shipments
    shipments: {
        list: '/shipments',
        create: '/shipments',
        detail: (id: number) => `/shipments/${id}`,
        close: (id: number) => `/shipments/${id}/close`,
        settle: (id: number) => `/shipments/${id}/settle`,
        unsettle: (id: number) => `/shipments/${id}/unsettle`,
        stock: '/shipments/stock',
    },

    // Customers
    customers: {
        list: '/customers',
        create: '/customers',
        detail: (id: number) => `/customers/${id}`,
        update: (id: number) => `/customers/${id}`,
        delete: (id: number) => `/customers/${id}`,
        statement: (id: number) => `/reports/customer/${id}`,
    },

    // Suppliers
    suppliers: {
        list: '/suppliers',
        create: '/suppliers',
        detail: (id: number) => `/suppliers/${id}`,
        update: (id: number) => `/suppliers/${id}`,
        statement: (id: number) => `/reports/supplier/${id}`,
    },

    // Expenses
    expenses: {
        list: '/expenses',
        create: '/expenses',
        detail: (id: number) => `/expenses/${id}`,
        cancel: (id: number) => `/expenses/${id}/cancel`,
    },

    // Returns
    returns: {
        list: '/returns',
        create: '/returns',
        detail: (id: number) => `/returns/${id}`,
        cancel: (id: number) => `/returns/${id}/cancel`,
    },

    // Products
    products: {
        list: '/products',
        detail: (id: number) => `/products/${id}`,
    },

    // Accounts
    accounts: {
        summary: '/accounts/summary',
        cashbox: '/cashbox',
        cashboxTransactions: '/cashbox/transactions',
        cashboxDeposit: '/cashbox/deposit',
        cashboxWithdraw: '/cashbox/withdraw',
        bank: '/bank',
        bankTransactions: '/bank/transactions',
        bankDeposit: '/bank/deposit',
        bankWithdraw: '/bank/withdraw',
        transfer: '/transfers',
    },

    // Reports
    reports: {
        daily: (date: string) => `/reports/daily/${date}/pdf`,
        customerStatement: (id: number) => `/reports/customer/${id}`,
        supplierStatement: (id: number) => `/reports/supplier/${id}`,
    },

    // Settings
    settings: {
        get: '/settings',
        update: '/settings',
    },
} as const;

