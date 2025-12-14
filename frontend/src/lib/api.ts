import { AuthResponse } from '@/types';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

// API Error interface matching backend format
export interface ApiError {
    success: false;
    error: {
        code: string;
        message: string;
        message_en: string;
        details?: Record<string, string[]>;
    };
}

// API Success interface
export interface ApiSuccess<T> {
    success: true;
    message?: string;
    data: T;
}

// تحسين 2025-12-13: Custom error class with error code support
export class ApiResponseError extends Error {
    public readonly code: string;
    public readonly messageAr: string;
    public readonly messageEn: string;
    public readonly details?: Record<string, string[]>;

    constructor(apiError: ApiError['error']) {
        super(apiError.message);
        this.name = 'ApiResponseError';
        this.code = apiError.code;
        this.messageAr = apiError.message;
        this.messageEn = apiError.message_en;
        this.details = apiError.details;
    }

    // Get message based on locale
    getMessage(locale: 'ar' | 'en' = 'ar'): string {
        return locale === 'ar' ? this.messageAr : this.messageEn;
    }
}

class ApiClient {
    private token: string | null = null;

    constructor() {
        if (typeof window !== 'undefined') {
            this.token = localStorage.getItem('token');
        }
    }

    setToken(token: string | null) {
        this.token = token;
        if (typeof window !== 'undefined') {
            if (token) {
                localStorage.setItem('token', token);
            } else {
                localStorage.removeItem('token');
            }
        }
    }

    getToken() {
        return this.token;
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers,
        };

        if (this.token) {
            (headers as Record<string, string>)['Authorization'] = `Bearer ${this.token}`;
        }

        const response = await fetch(`${API_URL}${endpoint}`, {
            ...options,
            headers,
        });

        if (response.status === 401) {
            this.setToken(null);
            if (typeof window !== 'undefined') {
                window.location.href = '/login';
            }
            throw new Error('Unauthorized');
        }

        const data = await response.json();

        if (!response.ok) {
            // تحسين 2025-12-13: استخدام ApiResponseError مع أكواد الخطأ
            if (data.error && data.error.code) {
                throw new ApiResponseError(data.error);
            }
            // Fallback for legacy error format
            throw new Error(data.message || data.error?.message || 'Something went wrong');
        }

        // Handle new success format - extract data if present
        if (data.success === true && data.data !== undefined) {
            return data.data;
        }

        return data;
    }

    // Auth
    async login(email: string, password: string): Promise<AuthResponse> {
        const response = await this.request<AuthResponse>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });
        this.setToken(response.token);
        return response;
    }

    async logout() {
        await this.request('/auth/logout', { method: 'POST' });
        this.setToken(null);
    }

    async getMe() {
        return this.request<AuthResponse['user']>('/auth/me');
    }

    // Dashboard
    async getDashboard() {
        return this.request<{
            customers_count: number;
            suppliers_count: number;
            total_receivables: number;
            total_payables: number;
            open_shipments: number;
            today_sales: number;
            today_collections: number;
            today_expenses: number;
        }>('/dashboard');
    }

    // Customers
    async getCustomers(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/customers${query}`);
    }

    async getCustomer(id: number) {
        return this.request(`/customers/${id}`);
    }

    async createCustomer(data: { name: string; phone?: string; address?: string }) {
        return this.request('/customers', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateCustomer(id: number, data: { name?: string; phone?: string; address?: string; is_active?: boolean }) {
        return this.request(`/customers/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteCustomer(id: number) {
        return this.request(`/customers/${id}`, { method: 'DELETE' });
    }

    async getCustomerStatement(id: number, params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/reports/customer/${id}${query}`);
    }

    // Suppliers
    async getSuppliers(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/suppliers${query}`);
    }

    async getSupplier(id: number) {
        return this.request(`/suppliers/${id}`);
    }

    async createSupplier(data: { name: string; phone?: string; address?: string }) {
        return this.request('/suppliers', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateSupplier(id: number, data: { name?: string; phone?: string; address?: string; is_active?: boolean }) {
        return this.request(`/suppliers/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteSupplier(id: number) {
        return this.request(`/suppliers/${id}`, { method: 'DELETE' });
    }

    // Invoices
    async getInvoices(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/invoices${query}`);
    }

    async getInvoice(id: number) {
        return this.request(`/invoices/${id}`);
    }

    async createInvoice(data: {
        customer_id: number;
        date: string;
        discount?: number;
        items: { product_id: number; quantity: number; unit_price: number; cartons?: number }[];
    }) {
        return this.request('/invoices', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async cancelInvoice(id: number) {
        return this.request(`/invoices/${id}/cancel`, { method: 'POST' });
    }

    // Collections
    async getCollections(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/collections${query}`);
    }

    async getCollection(id: number) {
        return this.request(`/collections/${id}`);
    }

    async createCollection(data: {
        customer_id: number;
        date: string;
        amount: number;
        payment_method: 'cash' | 'bank';
        // تصحيح 2025-12-13: استخدام oldest_first / newest_first بدلاً من auto
        distribution_method?: 'oldest_first' | 'newest_first' | 'manual';
        allocations?: { invoice_id: number; amount: number }[];
    }) {
        return this.request('/collections', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    // تصحيح 2025-12-13: إلغاء بدلاً من حذف (BR-COL-007)
    async cancelCollection(id: number) {
        return this.request(`/collections/${id}/cancel`, { method: 'POST' });
    }

    async getUnpaidInvoices(customerId: number) {
        return this.request(`/collections/unpaid-invoices?customer_id=${customerId}`);
    }

    // Shipments
    async getShipments(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/shipments${query}`);
    }

    async getShipment(id: number) {
        return this.request(`/shipments/${id}`);
    }

    async createShipment(data: {
        supplier_id: number;
        date: string;
        items: { product_id: number; weight_per_unit: number; weight_label?: string; cartons: number; initial_quantity: number; unit_cost: number }[];
    }) {
        return this.request('/shipments', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async deleteShipment(id: number) {
        return this.request(`/shipments/${id}`, { method: 'DELETE' });
    }

    async closeShipment(id: number) {
        return this.request(`/shipments/${id}/close`, { method: 'POST' });
    }

    async settleShipment(id: number, nextShipmentId?: number) {
        return this.request(`/shipments/${id}/settle`, {
            method: 'POST',
            body: nextShipmentId ? JSON.stringify({ next_shipment_id: nextShipmentId }) : undefined,
        });
    }

    async unsettleShipment(id: number) {
        return this.request(`/shipments/${id}/unsettle`, { method: 'POST' });
    }

    async getShipmentSettlementReport(id: number) {
        return this.request(`/shipments/${id}/settlement-report`);
    }

    async getStock(productId?: number) {
        const query = productId ? `?product_id=${productId}` : '';
        return this.request(`/shipments/stock${query}`);
    }

    // Products
    async getProducts(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/products${query}`);
    }

    async getProduct(id: number) {
        return this.request(`/products/${id}`);
    }

    async createProduct(data: { name: string; name_en?: string; category?: string; is_active?: boolean }) {
        return this.request('/products', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateProduct(id: number, data: { name?: string; name_en?: string; category?: string; is_active?: boolean }) {
        return this.request(`/products/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteProduct(id: number) {
        return this.request(`/products/${id}`, { method: 'DELETE' });
    }

    // Expenses
    async getExpenses(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/expenses${query}`);
    }

    async getExpense(id: number) {
        return this.request(`/expenses/${id}`);
    }

    async createExpense(data: {
        type: 'supplier' | 'company';
        supplier_id?: number;
        shipment_id?: number;
        date: string;
        amount: number;
        description: string;
        payment_method: 'cash' | 'bank';
        category?: string;
        notes?: string;
    }) {
        return this.request('/expenses', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateExpense(id: number, data: {
        type?: 'supplier' | 'company';
        supplier_id?: number;
        shipment_id?: number;
        date?: string;
        amount?: number;
        description?: string;
        payment_method?: 'cash' | 'bank';
        category?: string;
        notes?: string;
    }) {
        return this.request(`/expenses/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteExpense(id: number) {
        return this.request(`/expenses/${id}`, { method: 'DELETE' });
    }

    // Reports (NEW)
    async getDailyReport(date: string) {
        return this.request(`/reports/daily/${date}`);
    }

    async getShipmentReport(shipmentId: number) {
        return this.request(`/reports/shipment/${shipmentId}`);
    }

    // Settings (NEW)
    async getSettings() {
        return this.request('/settings');
    }

    async getSetting(key: string) {
        return this.request(`/settings/${key}`);
    }

    async updateSettings(settings: { key: string; value: string | number | boolean }[]) {
        return this.request('/settings', {
            method: 'PUT',
            body: JSON.stringify({ settings }),
        });
    }

    // Returns
    async getReturns(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/returns${query}`);
    }

    async getReturn(id: number) {
        return this.request(`/returns/${id}`);
    }

    async createReturn(data: {
        customer_id: number;
        items: { product_id: number; quantity: number; unit_price: number; shipment_item_id?: number }[];
        original_invoice_id?: number;
        notes?: string;
    }) {
        return this.request('/returns', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async cancelReturn(id: number) {
        return this.request(`/returns/${id}/cancel`, { method: 'POST' });
    }

    // Accounts
    async getAccounts() {
        return this.request('/accounts');
    }

    async getAccountsSummary() {
        return this.request('/accounts/summary');
    }

    async getAccount(id: number) {
        return this.request(`/accounts/${id}`);
    }

    async getAccountTransactions(id: number, params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/accounts/${id}/transactions${query}`);
    }

    // Transfers
    async getTransfers(params?: Record<string, string>) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request(`/transfers${query}`);
    }

    async getTransfer(id: number) {
        return this.request(`/transfers/${id}`);
    }

    async createTransfer(data: {
        from_account_id: number;
        to_account_id: number;
        amount: number;
        date: string;
        notes?: string;
    }) {
        return this.request('/transfers', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }
}

export const api = new ApiClient();
