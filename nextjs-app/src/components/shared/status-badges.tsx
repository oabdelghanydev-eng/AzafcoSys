'use client';

import { Badge } from '@/components/ui/badge';
import type { InvoiceStatus, ReturnStatus, ShipmentStatus, PaymentMethod, ExpenseType } from '@/types/api';

// =============================================================================
// Invoice Status Badge
// =============================================================================

const invoiceStatusConfig: Record<InvoiceStatus, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
    paid: { variant: 'default', label: 'Paid' },
    partially_paid: { variant: 'secondary', label: 'Partial' },
    unpaid: { variant: 'outline', label: 'Unpaid' },
    cancelled: { variant: 'destructive', label: 'Cancelled' },
};

export function InvoiceStatusBadge({ status }: { status: InvoiceStatus | string }) {
    const config = invoiceStatusConfig[status as InvoiceStatus] || { variant: 'outline' as const, label: status };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

// =============================================================================
// Shipment Status Badge
// =============================================================================

const shipmentStatusConfig: Record<ShipmentStatus, { variant: 'default' | 'secondary' | 'outline'; className: string }> = {
    open: { variant: 'default', className: 'bg-blue-500' },
    closed: { variant: 'secondary', className: 'bg-yellow-500 text-black' },
    settled: { variant: 'outline', className: 'bg-green-100 text-green-800 border-green-300' },
};

export function ShipmentStatusBadge({ status }: { status: ShipmentStatus | string }) {
    const config = shipmentStatusConfig[status as ShipmentStatus] || { variant: 'outline' as const, className: '' };
    return <Badge variant={config.variant} className={config.className}>{status}</Badge>;
}

// =============================================================================
// Return Status Badge
// =============================================================================

const returnStatusConfig: Record<ReturnStatus, { variant: 'default' | 'secondary' | 'destructive'; label: string }> = {
    completed: { variant: 'default', label: 'Completed' },
    pending: { variant: 'secondary', label: 'Pending' },
    cancelled: { variant: 'destructive', label: 'Cancelled' },
};

export function ReturnStatusBadge({ status }: { status: ReturnStatus | string }) {
    const config = returnStatusConfig[status as ReturnStatus] || { variant: 'secondary' as const, label: status };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

// =============================================================================
// Payment Method Badge
// =============================================================================

const paymentMethodStyles: Record<PaymentMethod, string> = {
    cash: 'bg-green-100 text-green-800',
    bank: 'bg-blue-100 text-blue-800',
};

export function PaymentMethodBadge({ method }: { method: PaymentMethod | string }) {
    const className = paymentMethodStyles[method as PaymentMethod] || '';
    const label = method === 'bank' || method === 'bank_transfer' ? 'Bank' : 'Cash';
    return <Badge variant="outline" className={className}>{label}</Badge>;
}

// =============================================================================
// Expense Type Badge
// =============================================================================

const expenseTypeStyles: Record<ExpenseType, string> = {
    company: 'bg-purple-100 text-purple-800',
    supplier: 'bg-blue-100 text-blue-800',
    supplier_payment: 'bg-orange-100 text-orange-800',
};

export function ExpenseTypeBadge({ type }: { type: ExpenseType | string }) {
    const className = expenseTypeStyles[type as ExpenseType] || '';
    const label = type.replace('_', ' ');
    return <Badge variant="outline" className={className}>{label}</Badge>;
}

// =============================================================================
// Active/Inactive Badge
// =============================================================================

export function ActiveBadge({ isActive }: { isActive: boolean }) {
    return (
        <Badge variant={isActive ? 'default' : 'secondary'}>
            {isActive ? 'Active' : 'Inactive'}
        </Badge>
    );
}
