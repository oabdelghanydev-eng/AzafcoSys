// API Types for Inventory System

export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    permissions: string[];
    avatar?: string;
}

export interface Customer {
    id: number;
    code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number;
    formatted_balance: string;
    is_active: boolean;
    notes?: string;
    invoices_count?: number;
    created_at: string;
    updated_at?: string;
}

export interface Supplier {
    id: number;
    code: string;
    name: string;
    phone?: string;
    address?: string;
    balance: number;
    formatted_balance?: string;
    is_active: boolean;
    notes?: string;
    shipments_count?: number;
    expenses_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface Product {
    id: number;
    name: string;
    name_en?: string;
    category?: string;
    is_active: boolean;
}

export interface Shipment {
    id: number;
    number: string;
    supplier: { id: number; name: string };
    date: string;
    status: 'open' | 'closed' | 'settled';
    total_cost: number;
    notes?: string;
    items?: ShipmentItem[];
    settled_at?: string;
    created_at: string;
}

export interface ShipmentItem {
    id: number;
    product: { id: number; name: string };
    weight_per_unit: number;
    weight_label?: string;
    cartons: number;
    initial_quantity: number;
    sold_quantity: number;
    remaining_quantity: number;
    wastage_quantity: number;
    unit_cost: number;
    total_cost: number;
}

export interface Invoice {
    id: number;
    invoice_number: string;
    customer: { id: number; name: string };
    date: string;
    type: 'sale' | 'wastage';
    status: 'active' | 'cancelled';
    subtotal: number;
    discount: number;
    total: number;
    paid_amount: number;
    balance: number;
    notes?: string;
    items?: InvoiceItem[];
    created_at: string;
}

export interface InvoiceItem {
    id: number;
    product: { id: number; name: string };
    shipment_item_id: number;
    cartons: number;
    quantity: number;
    unit_price: number;
    subtotal: number;
}

export interface Collection {
    id: number;
    receipt_number: string;
    customer: { id: number; name: string };
    date: string;
    amount: number;
    payment_method: 'cash' | 'bank';
    // تصحيح 2025-12-13: إضافة oldest_first و newest_first
    distribution_method: 'oldest_first' | 'newest_first' | 'manual';
    status: 'confirmed' | 'cancelled'; // تصحيح 2025-12-13
    allocated_amount: number;
    unallocated_amount: number;
    notes?: string;
    allocations?: CollectionAllocation[];
    created_at: string;
}

export interface CollectionAllocation {
    invoice_id: number;
    invoice_number: string;
    amount: number;
}

export interface Return {
    id: number;
    return_number: string;
    customer: { id: number; name: string };
    date: string;
    total_amount: number;
    status: 'active' | 'cancelled';
    notes?: string;
    items?: ReturnItem[];
}

export interface ReturnItem {
    id: number;
    product: { id: number; name: string };
    quantity: number;
    unit_price: number;
    subtotal: number;
}

export interface Expense {
    id: number;
    expense_number: string;
    type: 'supplier' | 'company';
    type_label: string;
    date: string;
    amount: number;
    payment_method: 'cash' | 'bank';
    payment_method_label: string;
    category?: string;
    description: string;
    notes?: string;
    supplier?: { id: number; name: string; code: string };
    shipment?: { id: number; number: string };
    created_at: string;
    updated_at?: string;
}

export interface DashboardStats {
    customers_count: number;
    suppliers_count: number;
    total_receivables: number;
    total_payables: number;
    open_shipments: number;
    today_sales: number;
    today_collections: number;
    today_expenses: number;
}

// Account types - تصحيح 2025-12-13
export interface Account {
    id: number;
    name: string;
    type: 'cashbox' | 'bank';
    balance: number;
    is_default: boolean;
    is_active: boolean;
}

export interface Transfer {
    id: number;
    from_account: { id: number; name: string };
    to_account: { id: number; name: string };
    amount: number;
    date: string;
    notes?: string;
    created_at: string;
}

// API Response types
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface AuthResponse {
    user: User;
    token: string;
}
