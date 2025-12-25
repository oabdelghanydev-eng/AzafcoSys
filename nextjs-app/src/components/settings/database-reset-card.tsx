'use client';

import { useState, useCallback } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, Loader2, Server, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { useAuthStore } from '@/stores/auth-store';

// =============================================================================
// Types
// =============================================================================

interface ResetDatabaseResponse {
    success: boolean;
    message?: string;
    message_en?: string;
    warning?: string;
    error?: {
        code: string;
        message: string;
        message_en: string;
    };
}

// =============================================================================
// Hook: useResetDatabase
// =============================================================================

function useResetDatabase() {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const reset = useCallback(async (password: string): Promise<boolean> => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await api.post<ResetDatabaseResponse>(
                endpoints.settings.resetDatabase,
                { password }
            );

            if (response.success) {
                return true;
            } else {
                setError(response.error?.message_en || 'Reset failed');
                return false;
            }
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Reset failed';
            setError(errorMessage);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return { reset, isLoading, error };
}

// =============================================================================
// Component: DatabaseResetCard
// =============================================================================

export function DatabaseResetCard() {
    const { user } = useAuthStore();
    const { reset, isLoading, error } = useResetDatabase();

    const [password, setPassword] = useState('');
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [confirmText, setConfirmText] = useState('');

    // Only show for admins
    if (!user?.is_admin) {
        return null;
    }

    const handleInitiateReset = () => {
        if (password.length < 8) {
            toast.error('Password must be at least 8 characters');
            return;
        }
        setShowConfirmDialog(true);
    };

    const handleConfirmReset = async () => {
        if (confirmText !== 'RESET') {
            toast.error('Please type RESET to confirm');
            return;
        }

        const success = await reset(password);

        if (success) {
            toast.success('Database reset completed! Please login again.');
            // Clear auth and redirect
            setTimeout(() => {
                window.location.href = '/login';
            }, 2000);
        } else {
            toast.error(error || 'Reset failed');
        }

        setShowConfirmDialog(false);
        setPassword('');
        setConfirmText('');
    };

    const handleCloseDialog = () => {
        setShowConfirmDialog(false);
        setConfirmText('');
    };

    return (
        <>
            <Card className="border-red-200 dark:border-red-900 bg-red-50/50 dark:bg-red-950/20">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-red-700 dark:text-red-400">
                        <ShieldAlert className="h-5 w-5" />
                        Danger Zone
                    </CardTitle>
                    <CardDescription className="text-red-600/80 dark:text-red-400/80">
                        These actions are destructive and cannot be undone
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-start gap-3 p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <Server className="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
                        <div className="space-y-1">
                            <h4 className="font-medium text-red-800 dark:text-red-300">
                                Reset Database
                            </h4>
                            <p className="text-sm text-red-700/80 dark:text-red-400/80">
                                This will delete ALL data and reseed with demo data.
                                All invoices, collections, customers, and shipments will be lost.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="reset-password" className="text-red-700 dark:text-red-400">
                            Admin Reset Password
                        </Label>
                        <Input
                            id="reset-password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Enter admin reset password"
                            className="border-red-300 dark:border-red-800"
                        />
                        <p className="text-xs text-red-600/80 dark:text-red-400/80">
                            This password is configured in the server&apos;s .env file
                        </p>
                    </div>

                    <Button
                        variant="destructive"
                        onClick={handleInitiateReset}
                        disabled={isLoading || password.length < 8}
                        className="w-full"
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Resetting...
                            </>
                        ) : (
                            <>
                                <AlertTriangle className="mr-2 h-4 w-4" />
                                Reset Database
                            </>
                        )}
                    </Button>
                </CardContent>
            </Card>

            {/* Confirmation Dialog */}
            <AlertDialog open={showConfirmDialog} onOpenChange={setShowConfirmDialog}>
                <AlertDialogContent className="border-red-200 dark:border-red-900">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2 text-red-700 dark:text-red-400">
                            <AlertTriangle className="h-5 w-5" />
                            Confirm Database Reset
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-3 text-sm text-muted-foreground">
                                <span className="block">
                                    This action will <strong>permanently delete</strong> all data including:
                                </span>
                                <ul className="list-disc list-inside text-sm space-y-1">
                                    <li>All invoices and collections</li>
                                    <li>All customer and supplier records</li>
                                    <li>All shipments and inventory</li>
                                    <li>All daily reports and expenses</li>
                                </ul>
                                <span className="block font-medium text-red-600 dark:text-red-400">
                                    Type &quot;RESET&quot; to confirm:
                                </span>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <Input
                        value={confirmText}
                        onChange={(e) => setConfirmText(e.target.value.toUpperCase())}
                        placeholder="Type RESET"
                        className="border-red-300 dark:border-red-800 text-center font-mono text-lg"
                    />

                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={handleCloseDialog}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmReset}
                            disabled={confirmText !== 'RESET' || isLoading}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {isLoading ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <AlertTriangle className="mr-2 h-4 w-4" />
                            )}
                            Reset Database
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
