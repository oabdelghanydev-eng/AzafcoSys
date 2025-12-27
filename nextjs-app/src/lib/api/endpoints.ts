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
        financialSummary: '/dashboard/financial-summary',
    },

    // Daily Report
    daily: {
        current: '/daily/current',
        available: '/daily/available',
        open: '/daily/open',
        close: '/daily/close',
        forceClose: '/daily/force-close',
    },

    // Alias for consistency
    dailyReports: {
        current: '/daily/current',
        availableDates: '/daily/available',
        open: '/daily/open',
        close: '/daily/close',
        forceClose: '/daily/force-close',
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
        // Financial
        profitLoss: '/reports/profit-loss',
        profitLossPdf: '/reports/profit-loss/pdf',
        cashFlow: '/reports/cash-flow',
        cashFlowPdf: '/reports/cash-flow/pdf',
        // Sales
        salesByProduct: '/reports/sales/by-product',
        salesByProductPdf: '/reports/sales/by-product/pdf',
        salesByCustomer: '/reports/sales/by-customer',
        salesByCustomerPdf: '/reports/sales/by-customer/pdf',
        // Customer
        customerAging: '/reports/customers/aging',
        customerAgingPdf: '/reports/customers/aging/pdf',
        customerBalances: '/reports/customers/balances',
        customerBalancesPdf: '/reports/customers/balances/pdf',
        // Inventory
        inventoryStock: '/reports/inventory/stock',
        inventoryStockPdf: '/reports/inventory/stock/pdf',
        inventoryMovement: '/reports/inventory/movement',
        inventoryMovementPdf: '/reports/inventory/movement/pdf',
        inventoryWastage: '/reports/inventory/wastage',
        inventoryWastagePdf: '/reports/inventory/wastage/pdf',
        // Supplier
        supplierBalances: '/reports/suppliers/balances',
        supplierBalancesPdf: '/reports/suppliers/balances/pdf',
        supplierPerformance: '/reports/suppliers/performance',
        supplierPerformancePdf: '/reports/suppliers/performance/pdf',
        supplierPayments: '/reports/suppliers/payments',
        supplierPaymentsPdf: '/reports/suppliers/payments/pdf',
    },

    // Settings
    settings: {
        get: '/settings',
        update: '/settings',
        resetDatabase: '/settings/reset-database',
    },

    // Users
    users: {
        list: '/users',
        create: '/users',
        detail: (id: number) => `/users/${id}`,
        update: (id: number) => `/users/${id}`,
        delete: (id: number) => `/users/${id}`,
        permissions: (id: number) => `/users/${id}/permissions`,
        password: (id: number) => `/users/${id}/password`,
        lock: (id: number) => `/users/${id}/lock`,
        unlock: (id: number) => `/users/${id}/unlock`,
    },

    // Permissions
    permissions: {
        list: '/permissions',
    },
} as const;

