/**
 * TypeScript Types - نظام إدارة المبيعات
 * Generated from Backend API
 * Date: 2025-12-22
 */

// ============================================
// AUTH
// ============================================

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin: boolean;
    permissions: string[];
    is_locked: boolean;
    created_at: string;
}

export interface AuthResponse {
    success: boolean;
    data: {
        token: string;
        user: User;
    };
}

export interface LoginCredentials {
    email: string;
    password: string;
}

// ============================================
// CUSTOMERS
// ============================================

export interface Customer {
    id: number;
    code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number; // (+) مديون | (0) خالص | (-) دائن
    is_active: boolean;
    notes?: string;
    created_at: string;
    updated_at: string;
}

export interface CreateCustomerData {
    name: string;
    phone?: string;
    address?: string;
    opening_balance?: number;
    notes?: string;
}

export interface UpdateCustomerData extends Partial<CreateCustomerData> { }

// ============================================
// SUPPLIERS
// ============================================

export interface Supplier {
    id: number;
    code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number; // (+) له عندنا | (0) خالص | (-) علينا
    is_active: boolean;
    notes?: string;
    created_at: string;
    updated_at: string;
}

export interface CreateSupplierData {
    name: string;
    phone?: string;
    address?: string;
    opening_balance?: number;
}

// ============================================
// PRODUCTS
// ============================================

export interface Product {
    id: number;
    name: string;
    name_en?: string;
    category?: string;
    description?: string;
    is_active: boolean;
    created_at: string;
}

// ============================================
// SHIPMENTS
// ============================================

export type ShipmentStatus = 'open' | 'closed' | 'settled';

export interface Shipment {
    id: number;
    number: string;
    supplier_id: number;
    supplier?: Supplier;
    date: string;
    driver_name?: string;
    vehicle_number?: string;
    status: ShipmentStatus;
    closed_at?: string;
    settled_at?: string;
    total_sales: number;
    total_returns: number;
    total_wastage: number;
    total_carryover_out: number;
    total_supplier_expenses: number;
    final_balance: number;
    notes?: string;
    items?: ShipmentItem[];
    created_by: number;
    created_at: string;
}

export interface ShipmentItem {
    id: number;
    shipment_id: number;
    product_id: number;
    product?: Product;
    weight_label?: string;
    weight_per_unit: number;
    cartons: number;
    initial_quantity: number;
    remaining_quantity: number;
    sold_quantity: number;
    wastage_quantity: number;
    returned_quantity: number;
    carryover_in_quantity: number;
    carryover_out_quantity: number;
}

export interface CreateShipmentData {
    supplier_id: number;
    date: string;
    driver_name?: string;
    vehicle_number?: string;
    notes?: string;
    items: CreateShipmentItemData[];
}

export interface CreateShipmentItemData {
    product_id: number;
    cartons: number;
    weight_per_unit: number;
    weight_label?: string;
    unit_cost?: number;
}

export interface StockItem {
    product_id: number;
    product_name: string;
    total_quantity: number;
    items: {
        id: number;
        shipment: { number: string };
        remaining_cartons: number;
        weight_per_unit: number;
    }[];
}

// ============================================
// INVOICES
// ============================================

export type InvoiceType = 'sale' | 'wastage';
export type InvoiceStatus = 'active' | 'cancelled';

export interface Invoice {
    id: number;
    invoice_number: string;
    customer_id: number;
    customer?: Customer;
    date: string;
    type: InvoiceType;
    subtotal: number;
    discount: number;
    total: number;
    paid_amount: number;
    balance: number;
    status: InvoiceStatus;
    notes?: string;
    items?: InvoiceItem[];
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface InvoiceItem {
    id: number;
    invoice_id: number;
    product_id: number;
    product?: Product;
    shipment_item_id: number;
    shipment_id: number;
    cartons: number;
    quantity: number; // الوزن بالكيلو
    price_per_kg: number;
    total: number;
    is_wastage: boolean;
}

export interface CreateInvoiceData {
    customer_id: number;
    date?: string;
    discount?: number;
    notes?: string;
    items: CreateInvoiceItemData[];
}

export interface CreateInvoiceItemData {
    product_id: number;
    cartons: number;
    total_weight: number;
    price: number; // سعر الكيلو
}

export interface InvoiceFilters {
    customer_id?: number;
    date_from?: string;
    date_to?: string;
    status?: InvoiceStatus;
    unpaid_only?: boolean;
    page?: number;
    per_page?: number;
}

// ============================================
// COLLECTIONS
// ============================================

export type PaymentMethod = 'cash' | 'bank';
export type DistributionMethod = 'oldest_first' | 'newest_first' | 'manual';
export type CollectionStatus = 'confirmed' | 'cancelled';

export interface Collection {
    id: number;
    receipt_number: string;
    customer_id: number;
    customer?: Customer;
    date: string;
    amount: number;
    payment_method: PaymentMethod;
    distribution_method: DistributionMethod;
    invoice_id?: number;
    status: CollectionStatus;
    notes?: string;
    allocations?: CollectionAllocation[];
    created_by: number;
    created_at: string;
}

export interface CollectionAllocation {
    id: number;
    collection_id: number;
    invoice_id: number;
    invoice?: Invoice;
    amount: number;
}

export interface CreateCollectionData {
    customer_id: number;
    date?: string;
    amount: number;
    payment_method: PaymentMethod;
    distribution_method?: DistributionMethod;
    invoice_id?: number; // for manual distribution
    notes?: string;
}

// ============================================
// RETURNS
// ============================================

export type ReturnStatus = 'active' | 'cancelled';

export interface Return {
    id: number;
    return_number: string;
    customer_id: number;
    customer?: Customer;
    original_invoice_id?: number;
    date: string;
    total_amount: number;
    status: ReturnStatus;
    notes?: string;
    items?: ReturnItem[];
    created_by: number;
    created_at: string;
}

export interface ReturnItem {
    id: number;
    return_id: number;
    product_id: number;
    product?: Product;
    original_invoice_item_id?: number;
    target_shipment_item_id: number;
    quantity: number;
    price_per_kg: number;
    subtotal: number;
}

export interface CreateReturnData {
    customer_id: number;
    original_invoice_id?: number;
    notes?: string;
    items: CreateReturnItemData[];
}

export interface CreateReturnItemData {
    invoice_item_id: number;
    cartons: number;
}

// ============================================
// EXPENSES
// ============================================

export type ExpenseType = 'company' | 'supplier' | 'supplier_payment';
export type ExpenseStatus = 'confirmed' | 'cancelled';

export interface Expense {
    id: number;
    expense_number: string;
    type: ExpenseType;
    supplier_id?: number;
    supplier?: Supplier;
    shipment_id?: number;
    category?: string;
    date: string;
    amount: number;
    description: string;
    payment_method: PaymentMethod;
    status: ExpenseStatus;
    notes?: string;
    created_by: number;
    created_at: string;
}

export interface CreateExpenseData {
    type: ExpenseType;
    supplier_id?: number;
    shipment_id?: number;
    date?: string;
    amount: number;
    description: string;
    payment_method: PaymentMethod;
    notes?: string;
}

// ============================================
// TREASURY
// ============================================

export type AccountType = 'cashbox' | 'bank';
export type TransactionType = 'collection' | 'expense' | 'deposit' | 'withdrawal' | 'transfer_in' | 'transfer_out';

export interface Account {
    id: number;
    type: AccountType;
    name: string;
    balance: number;
}

export interface Transaction {
    id: number;
    type: TransactionType;
    amount: number;
    balance_after: number;
    reference_type?: string;
    reference_id?: number;
    date: string;
    description?: string;
    created_by: number;
    created_at: string;
}

export interface Transfer {
    id: number;
    from_account_id: number;
    to_account_id: number;
    amount: number;
    date: string;
    notes?: string;
    created_by: number;
    created_at: string;
}

export interface CreateTransferData {
    from_account_id: number;
    to_account_id: number;
    amount: number;
    notes?: string;
}

// ============================================
// DAILY REPORTS
// ============================================

export type DailyReportStatus = 'open' | 'closed';

export interface DailyReport {
    id: number;
    date: string;
    status: DailyReportStatus;
    total_sales: number;
    total_collections: number;
    total_expenses: number;
    cash_balance: number;
    bank_balance: number;
    invoices_count: number;
    collections_count: number;
    expenses_count: number;
    notes?: string;
    closed_by?: number;
    created_at: string;
}

export interface DailySummary {
    date: string;
    sales: {
        count: number;
        total: number;
        discount: number;
    };
    collections: {
        count: number;
        total: number;
        cash: number;
        bank: number;
    };
    expenses: {
        count: number;
        total: number;
        cash: number;
        bank: number;
        supplier: number;
        company: number;
    };
    net: {
        cash: number;
    };
}

// ============================================
// DASHBOARD
// ============================================

export interface DashboardStats {
    customers_count: number;
    suppliers_count: number;
    products_count: number;
    total_receivables: number;
    total_payables: number;
    open_shipments: number;
    today_sales: number;
    today_sales_count: number;
    today_collections: number;
    today_expenses: number;
    today_net_cash: number;
    top_debtors: { id: number; name: string; balance: number }[];
}

// ============================================
// STATEMENTS
// ============================================

export interface StatementTransaction {
    type: 'invoice' | 'collection' | 'return' | 'credit_note' | 'debit_note';
    date: string;
    reference: string;
    debit: number;
    credit: number;
    balance: number;
}

export interface CustomerStatement {
    customer: Customer;
    opening_balance: number;
    transactions: StatementTransaction[];
    closing_balance: number;
}

export interface SupplierStatement {
    supplier: Supplier;
    opening_balance: number;
    transactions: StatementTransaction[];
    closing_balance: number;
}

// ============================================
// ALERTS
// ============================================

export type AlertType = 'price_anomaly' | 'shipment_delay' | 'fifo_error' | 'customer_risk';
export type AlertStatus = 'new' | 'read' | 'resolved';

export interface Alert {
    id: number;
    type: AlertType;
    title: string;
    description: string;
    context: Record<string, unknown>;
    status: AlertStatus;
    resolved_by?: number;
    created_at: string;
}

// ============================================
// API RESPONSES
// ============================================

export interface ApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface ApiError {
    success: false;
    error: {
        code: string;
        message: string;
        message_en?: string;
        details?: Record<string, string[]>;
    };
}

// ============================================
// SETTINGS
// ============================================

export interface Settings {
    company_name: string;
    company_phone: string;
    company_address: string;
    company_logo: string;
    currency_symbol: string;
    company_commission_rate: number;
    price_anomaly_threshold: number;
    edit_window_days: number;
    backdated_days: number;
}
