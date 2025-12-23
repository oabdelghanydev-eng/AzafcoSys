'use client';

import { ReactNode } from 'react';
import { useAuthStore } from '@/stores/auth-store';

interface PermissionGateProps {
    permission?: string;
    permissions?: string[];
    requireAll?: boolean;
    children: ReactNode;
    fallback?: ReactNode;
}

/**
 * Conditionally render children based on user permissions
 */
export function PermissionGate({
    permission,
    permissions,
    requireAll = false,
    children,
    fallback = null,
}: PermissionGateProps) {
    const { hasPermission, hasAnyPermission, hasAllPermissions } = useAuthStore();

    let hasAccess = false;

    if (permission) {
        hasAccess = hasPermission(permission);
    } else if (permissions) {
        hasAccess = requireAll
            ? hasAllPermissions(permissions)
            : hasAnyPermission(permissions);
    } else {
        hasAccess = true; // No permission required
    }

    if (!hasAccess) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}
