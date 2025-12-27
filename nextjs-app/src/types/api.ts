// =============================================================================
// API Types - Central Type Definitions
// =============================================================================

/**
 * Generic API response wrapper
 */
export interface ApiResponse<T> {
    data: T;
    message?: string;
    meta?: {
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
    };
}

// =============================================================================
// Customer Types
// =============================================================================

export interface Customer {
    id: number;
    customer_code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number;
    opening_balance?: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface CreateCustomerData {
    name: string;
    phone?: string;
    address?: string;
    opening_balance?: number;
}

export interface UpdateCustomerData extends Partial<CreateCustomerData> {
    is_active?: boolean;
}

// =============================================================================
// Supplier Types
// =============================================================================

export interface Supplier {
    id: number;
    supplier_code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number;
    opening_balance?: number;
    is_active?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface CreateSupplierData {
    name: string;
    phone?: string;
    address?: string;
    opening_balance?: number;
}

export interface UpdateSupplierData extends Partial<CreateSupplierData> {
    is_active?: boolean;
}

// =============================================================================
// Product Types
// =============================================================================

export interface Product {
    id: number;
    name: string;
    name_en?: string;
    name_ar?: string;
    description?: string;
    is_active?: boolean;
}

// =============================================================================
// Invoice Types
// =============================================================================

export type InvoiceStatus = 'paid' | 'partially_paid' | 'unpaid' | 'cancelled';

export interface Invoice {
    id: number;
    invoice_number: string;
    customer: Pick<Customer, 'id' | 'name' | 'phone'>;
    date: string;
    total: number;
    subtotal?: number;
    paid: number;
    balance: number;
    discount: number;
    status: InvoiceStatus;
    items: InvoiceItem[];
    allocations?: CollectionAllocation[];
    payments?: Payment[];
    created_at: string;
    updated_at?: string;
}

export interface InvoiceItem {
    id: number;
    product: Pick<Product, 'id' | 'name'>;
    cartons: number;
    quantity: number; // total_weight
    unit_price: number; // price per kg
    subtotal: number; // line_total
}

export interface CreateInvoiceData {
    customer_id: number;
    date: string;
    items: CreateInvoiceItemData[];
    discount?: number;
}

export interface CreateInvoiceItemData {
    product_id: number;
    cartons: number;
    total_weight: number;
    price: number;
}

// =============================================================================
// Collection Types
// =============================================================================

export type PaymentMethod = 'cash' | 'bank';
export type DistributionMethod = 'auto' | 'manual';

export interface Collection {
    id: number;
    receipt_number: string;
    customer: Pick<Customer, 'id' | 'name'>;
    date: string;
    amount: number;
    payment_method: PaymentMethod;
    distribution_method: DistributionMethod;
    status: string;
    allocations: CollectionAllocation[];
    notes?: string;
    created_at?: string;
}

export interface CollectionAllocation {
    invoice_id: number;
    invoice_number?: string;
    amount: number;
    collection?: Pick<Collection, 'id' | 'receipt_number'>;
    created_at?: string;
}

export interface CreateCollectionData {
    customer_id: number;
    date: string;
    amount: number;
    payment_method: PaymentMethod;
    distribution_method?: DistributionMethod;
    notes?: string;
    allocations?: { invoice_id: number; amount: number }[];
}

export interface Payment {
    id: number;
    amount: number;
    date?: string;
    created_at?: string;
    collection?: Pick<Collection, 'id' | 'receipt_number'>;
}

// =============================================================================
// Expense Types
// =============================================================================

export type ExpenseType = 'company' | 'supplier' | 'supplier_payment';
export type ExpenseStatus = 'completed' | 'cancelled';

export interface Expense {
    id: number;
    expense_number?: string;
    date: string;
    type: ExpenseType;
    description: string;
    amount: number;
    payment_method: PaymentMethod;
    status: ExpenseStatus;
    supplier?: Pick<Supplier, 'id' | 'name'>;
    notes?: string;
    created_at?: string;
}

export interface CreateExpenseData {
    date: string;
    type: ExpenseType;
    description: string;
    amount: number;
    payment_method: PaymentMethod;
    supplier_id?: number;
    notes?: string;
}

// =============================================================================
// Shipment Types
// =============================================================================

export type ShipmentStatus = 'open' | 'closed' | 'settled';

export interface Shipment {
    id: number;
    supplier: Pick<Supplier, 'id' | 'name'>;
    date: string;
    status: ShipmentStatus;
    total_cartons?: number;
    total_weight?: number;
    items: ShipmentItem[];
    notes?: string;
    created_at?: string;
}

export interface ShipmentItem {
    id: number;
    product: Pick<Product, 'id' | 'name'>;
    cartons: number;
    weight_per_unit: number;
    remaining_cartons?: number;
}

export interface CreateShipmentData {
    supplier_id: number;
    date: string;
    items: CreateShipmentItemData[];
    notes?: string;
}

export interface CreateShipmentItemData {
    product_id: number;
    cartons: number;
    weight_per_unit: number;
}

// Stock from shipments
export interface StockItem {
    product_id: number;
    product_name: string;
    total_quantity: number;
    remaining_cartons?: number;
    items?: ShipmentItem[];
}

// =============================================================================
// Return Types
// =============================================================================

export type ReturnStatus = 'completed' | 'pending' | 'cancelled';

export interface Return {
    id: number;
    return_number?: string;
    customer: Pick<Customer, 'id' | 'name' | 'customer_code'>;
    date: string;
    total: number;
    total_amount?: number; // alias for compatibility
    status: ReturnStatus;
    items: ReturnItem[];
    invoice?: Pick<Invoice, 'id' | 'invoice_number'>;
    notes?: string;
    created_at?: string;
}

export interface ReturnItem {
    id: number;
    product: Pick<Product, 'id' | 'name'>;
    cartons: number;
    weight: number;
    quantity?: number; // alias for cartons or units
    price: number;
    unit_price?: number; // alias for price
    subtotal: number;
    total?: number; // alias for subtotal
}

export interface CreateReturnData {
    customer_id: number;
    original_invoice_id: number; // Required: Link to original invoice for qty/price validation
    items: CreateReturnItemData[];
    notes?: string;
}

export interface CreateReturnItemData {
    product_id: number;
    quantity: number;     // Weight in kg (backend expects 'quantity')
    unit_price: number;   // Price per kg (backend expects 'unit_price')
    shipment_item_id?: number; // Optional: link to shipment item
}

// =============================================================================
// Account Types
// =============================================================================

export type TransactionType = 'deposit' | 'withdraw' | 'transfer';

export interface Account {
    id: number;
    name: string;
    type: 'cashbox' | 'bank';
    balance: number;
}

export interface AccountsSummary {
    cashbox: { balance: number };
    bank: { balance: number };
    total: number;
}

export interface Transaction {
    id: number;
    date: string;
    type: TransactionType;
    amount: number;
    notes?: string;
    account?: string;
    created_at?: string;
}

export interface TransferData {
    from_account_id: number;
    to_account_id: number;
    amount: number;
    notes?: string;
}

// =============================================================================
// Daily Report Types
// =============================================================================

export type DailyReportStatus = 'open' | 'closed';

export interface DailyReport {
    id?: number;
    date: string;
    status: DailyReportStatus;
    invoices_count?: number;
    collections_count?: number;
    expenses_count?: number;
    total_sales?: number;
    total_collections?: number;
    total_expenses?: number;
    opening_balance?: number;
    closing_balance?: number;
}

export interface AvailableDate {
    date: string;
    day_name?: string;
}

// =============================================================================
// Dashboard Types
// =============================================================================

export interface DashboardStats {
    today_sales: number;
    today_collections: number;
    invoices_count: number;
    cashbox_balance: number;
}

export interface DashboardActivity {
    invoices: Invoice[];
    collections: Collection[];
    expenses: Expense[];
}

// =============================================================================
// Settings Types
// =============================================================================

export interface Settings {
    company_name?: string;
    phone?: string;
    address?: string;
    currency_symbol?: string;
    commission_rate?: number;
}

export interface UpdateSettingsData {
    company_name?: string;
    phone?: string;
    address?: string;
    currency_symbol?: string;
    commission_rate?: number;
}

// =============================================================================
// Auth Types
// =============================================================================

export interface User {
    id: number;
    name: string;
    email: string;
    role?: string;
    permissions?: string[];
}

export interface LoginData {
    email: string;
    password: string;
}

export interface AuthResponse {
    user: User;
    token: string;
}
