'use client';

import { useState, useEffect } from 'react';
import { Loader2, CheckCircle, Shield, Eye, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { usePermissions, useUpdatePermissions } from '@/hooks/api/use-users';
import { toast } from 'sonner';

interface PermissionsEditorProps {
    userId: number;
    userName: string;
    currentPermissions: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

// Permission labels
const PERMISSION_LABELS: Record<string, string> = {
    view: 'View',
    create: 'Create',
    edit: 'Edit',
    delete: 'Delete',
    cancel: 'Cancel',
    close: 'Close',
    deposit: 'Deposit',
    withdraw: 'Withdraw',
    transfer: 'Transfer',
    adjust: 'Adjust',
    wastage: 'Wastage',
    daily: 'Daily',
    settlement: 'Settlement',
    customers: 'Customers',
    suppliers: 'Suppliers',
    inventory: 'Inventory',
    export_pdf: 'PDF',
    export_excel: 'Excel',
    share: 'Share',
    reopen: 'Reopen',
    unlock: 'Unlock',
    approve: 'Approve',
};

// Module labels
const MODULE_LABELS: Record<string, string> = {
    invoices: 'Invoices',
    collections: 'Collections',
    expenses: 'Expenses',
    shipments: 'Shipments',
    inventory: 'Inventory',
    cashbox: 'Cashbox',
    bank: 'Bank',
    customers: 'Customers',
    suppliers: 'Suppliers',
    products: 'Products',
    reports: 'Reports',
    daily: 'Daily Reports',
    users: 'Users',
    settings: 'Settings',
    corrections: 'Corrections',
};

// Module icons
const MODULE_ICONS: Record<string, React.ReactNode> = {
    invoices: '📋',
    collections: '💰',
    expenses: '💸',
    shipments: '📦',
    inventory: '🏭',
    cashbox: '💵',
    bank: '🏦',
    customers: '👥',
    suppliers: '🚚',
    products: '🍎',
    reports: '📊',
    daily: '📅',
    users: '👤',
    settings: '⚙️',
    corrections: '✏️',
};

export function PermissionsEditor({
    userId,
    userName,
    currentPermissions,
    open,
    onOpenChange,
}: PermissionsEditorProps) {
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>(currentPermissions);
    const { data: permissionsData, isLoading: permissionsLoading } = usePermissions();
    const updatePermissions = useUpdatePermissions();

    // Reset when opening
    useEffect(() => {
        if (open) {
            setSelectedPermissions(currentPermissions);
        }
    }, [open, currentPermissions]);

    const handleToggle = (permission: string) => {
        setSelectedPermissions(prev =>
            prev.includes(permission)
                ? prev.filter(p => p !== permission)
                : [...prev, permission]
        );
    };

    const handleSelectAll = () => {
        if (permissionsData?.permissions) {
            setSelectedPermissions(permissionsData.permissions);
        }
    };

    const handleClearAll = () => {
        setSelectedPermissions([]);
    };

    const handleSalesPreset = () => {
        // Sales role: invoices, collections view, customers view
        const salesPermissions = [
            'invoices.view', 'invoices.create', 'invoices.edit',
            'collections.view', 'collections.create',
            'customers.view',
            'cashbox.view',
        ];
        setSelectedPermissions(salesPermissions);
    };

    const handleViewOnlyPreset = () => {
        if (permissionsData?.permissions) {
            setSelectedPermissions(permissionsData.permissions.filter(p => p.endsWith('.view')));
        }
    };

    const handleSave = async () => {
        try {
            await updatePermissions.mutateAsync({
                id: userId,
                permissions: selectedPermissions,
            });
            toast.success('Permissions updated successfully');
            onOpenChange(false);
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to update permissions');
        }
    };

    const grouped = permissionsData?.grouped || {};

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        Manage Permissions - {userName}
                    </DialogTitle>
                    <DialogDescription>
                        Select the appropriate permissions for this user
                    </DialogDescription>
                </DialogHeader>

                {permissionsLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin" />
                    </div>
                ) : (
                    <>
                        {/* Quick Actions */}
                        <div className="flex flex-wrap gap-2 py-3 border-b">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSelectAll}
                                className="text-xs"
                            >
                                <CheckCircle className="h-3 w-3 mr-1" />
                                Select All
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleClearAll}
                                className="text-xs"
                            >
                                Clear All
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSalesPreset}
                                className="text-xs"
                            >
                                <ShieldCheck className="h-3 w-3 mr-1" />
                                Sales Rep
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleViewOnlyPreset}
                                className="text-xs"
                            >
                                <Eye className="h-3 w-3 mr-1" />
                                View Only
                            </Button>
                        </div>

                        {/* Permissions Grid */}
                        <div className="flex-1 overflow-y-auto py-4 space-y-4">
                            {Object.entries(grouped).map(([moduleKey, moduleData]) => (
                                <div
                                    key={moduleKey}
                                    className="border rounded-lg p-4 bg-muted/30"
                                >
                                    <div className="flex items-center gap-2 mb-3">
                                        <span className="text-lg">
                                            {MODULE_ICONS[moduleKey] || '📁'}
                                        </span>
                                        <h4 className="font-semibold">
                                            {MODULE_LABELS[moduleKey] || moduleData.label}
                                        </h4>
                                        <span className="text-xs text-muted-foreground">
                                            ({moduleData.permissions.length})
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap gap-4">
                                        {moduleData.permissions.map((perm) => {
                                            const fullPerm = `${moduleKey}.${perm}`;
                                            const isSelected = selectedPermissions.includes(fullPerm);
                                            return (
                                                <div
                                                    key={fullPerm}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={fullPerm}
                                                        checked={isSelected}
                                                        onCheckedChange={() => handleToggle(fullPerm)}
                                                    />
                                                    <Label
                                                        htmlFor={fullPerm}
                                                        className={cn(
                                                            "text-sm cursor-pointer",
                                                            isSelected && "font-medium text-primary"
                                                        )}
                                                    >
                                                        {PERMISSION_LABELS[perm] || perm}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-between pt-4 border-t">
                            <p className="text-sm text-muted-foreground">
                                {selectedPermissions.length} permissions selected
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleSave}
                                    disabled={updatePermissions.isPending}
                                >
                                    {updatePermissions.isPending && (
                                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    )}
                                    Save Permissions
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
