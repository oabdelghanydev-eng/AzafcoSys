'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Save, Loader2, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { DatabaseResetCard } from '@/components/settings/database-reset-card';
import { PermissionGate } from '@/components/shared/permission-gate';
import { useSettings, useUpdateSettings } from '@/hooks/api/use-settings';

// Fallback component for unauthorized access
function UnauthorizedAccess() {
    return (
        <div className="flex flex-col items-center justify-center min-h-[400px] text-center">
            <ShieldAlert className="h-16 w-16 text-destructive mb-4" />
            <h2 className="text-2xl font-bold mb-2">Access Denied</h2>
            <p className="text-muted-foreground max-w-md">
                You do not have permission to access system settings.
                Please contact an administrator if you believe this is an error.
            </p>
        </div>
    );
}

export default function SettingsPage() {
    return (
        <PermissionGate permission="admin.settings" fallback={<UnauthorizedAccess />}>
            <SettingsContent />
        </PermissionGate>
    );
}

function SettingsContent() {
    const { data: settings, isLoading, error, refetch } = useSettings();
    const updateSettings = useUpdateSettings();

    // Use settings data directly for initial form values
    const initialCompanyName = settings?.company_name || '';
    const initialPhone = settings?.phone || '';
    const initialAddress = settings?.address || '';
    const initialCurrencySymbol = settings?.currency_symbol || 'QAR';
    const initialCommissionRate = settings?.commission_rate?.toString() || '';

    const [companyName, setCompanyName] = useState(initialCompanyName);
    const [phone, setPhone] = useState(initialPhone);
    const [address, setAddress] = useState(initialAddress);
    const [currencySymbol, setCurrencySymbol] = useState(initialCurrencySymbol);
    const [commissionRate, setCommissionRate] = useState(initialCommissionRate);
    const [isInitialized, setIsInitialized] = useState(false);

    // Initialize form when data first arrives
    if (settings && !isInitialized) {
        setCompanyName(settings.company_name || '');
        setPhone(settings.phone || '');
        setAddress(settings.address || '');
        setCurrencySymbol(settings.currency_symbol || 'QAR');
        setCommissionRate(settings.commission_rate?.toString() || '');
        setIsInitialized(true);
    }

    const handleSave = async () => {
        try {
            await updateSettings.mutateAsync({
                company_name: companyName,
                phone,
                address,
                currency_symbol: currencySymbol,
                commission_rate: parseFloat(commissionRate) || 0,
            });
            toast.success('Settings saved successfully');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to save settings');
        }
    };

    if (isLoading) {
        return <LoadingState message="Loading settings..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load settings"
                message="Could not fetch settings"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6 max-w-2xl">
            {/* Page Header */}
            <div>
                <h1 className="text-2xl font-bold">Settings</h1>
                <p className="text-muted-foreground">
                    Manage application settings
                </p>
            </div>

            {/* Company Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Company Information</CardTitle>
                    <CardDescription>
                        This appears on invoices and reports
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label>Company Name</Label>
                        <Input
                            value={companyName}
                            onChange={(e) => setCompanyName(e.target.value)}
                            placeholder="Your Company Name"
                            className="touch-target"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Phone</Label>
                        <Input
                            type="tel"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="Contact phone"
                            className="touch-target"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Address</Label>
                        <Input
                            value={address}
                            onChange={(e) => setAddress(e.target.value)}
                            placeholder="Company address"
                            className="touch-target"
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Financial Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>Financial Settings</CardTitle>
                    <CardDescription>
                        Currency and commission settings
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label>Currency Symbol</Label>
                        <Input
                            value={currencySymbol}
                            onChange={(e) => setCurrencySymbol(e.target.value)}
                            placeholder="QAR"
                            className="touch-target"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Commission Rate (%)</Label>
                        <Input
                            type="number"
                            inputMode="decimal"
                            value={commissionRate}
                            onChange={(e) => setCommissionRate(e.target.value)}
                            placeholder="0.00"
                            className="touch-target"
                        />
                        <p className="text-xs text-muted-foreground">
                            Default commission rate for shipment settlements
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <Button
                onClick={handleSave}
                disabled={updateSettings.isPending}
                className="w-full touch-target"
            >
                {updateSettings.isPending ? (
                    <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Saving...
                    </>
                ) : (
                    <>
                        <Save className="mr-2 h-4 w-4" />
                        Save Settings
                    </>
                )}
            </Button>

            {/* Danger Zone - Admin Only */}
            <DatabaseResetCard />
        </div>
    );
}
