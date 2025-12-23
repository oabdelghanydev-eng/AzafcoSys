'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Wallet, Building2, ArrowRightLeft, TrendingUp, TrendingDown, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatCard } from '@/components/shared/stat-card';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useAccountsSummary, useCashboxTransactions, useTransfer } from '@/hooks/api/use-accounts';

function TransferDialog() {
    const [amount, setAmount] = useState('');
    const [fromAccount, setFromAccount] = useState('');
    const [toAccount, setToAccount] = useState('');
    const [notes, setNotes] = useState('');
    const [open, setOpen] = useState(false);
    const transfer = useTransfer();

    const handleTransfer = async () => {
        if (!amount || !fromAccount || !toAccount) {
            toast.error('Please fill all fields');
            return;
        }
        if (fromAccount === toAccount) {
            toast.error('Cannot transfer to the same account');
            return;
        }

        try {
            await transfer.mutateAsync({
                from_account_id: fromAccount === 'cashbox' ? 1 : 2,
                to_account_id: toAccount === 'cashbox' ? 1 : 2,
                amount: parseFloat(amount),
                notes,
            });
            toast.success('Transfer completed');
            setOpen(false);
            setAmount('');
            setNotes('');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Transfer failed');
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="touch-target">
                    <ArrowRightLeft className="mr-2 h-4 w-4" />
                    Transfer
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Transfer Funds</DialogTitle>
                </DialogHeader>
                <div className="space-y-4 pt-4">
                    <div className="space-y-2">
                        <Label>From Account</Label>
                        <Select value={fromAccount} onValueChange={setFromAccount}>
                            <SelectTrigger className="touch-target">
                                <SelectValue placeholder="Select account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cashbox">Cashbox</SelectItem>
                                <SelectItem value="bank">Bank</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>To Account</Label>
                        <Select value={toAccount} onValueChange={setToAccount}>
                            <SelectTrigger className="touch-target">
                                <SelectValue placeholder="Select account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cashbox">Cashbox</SelectItem>
                                <SelectItem value="bank">Bank</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Amount</Label>
                        <Input
                            type="number"
                            inputMode="decimal"
                            placeholder="0.00"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            className="touch-target"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Notes (optional)</Label>
                        <Input
                            placeholder="Transfer notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="touch-target"
                        />
                    </div>
                    <Button
                        onClick={handleTransfer}
                        disabled={transfer.isPending}
                        className="w-full touch-target"
                    >
                        {transfer.isPending ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : null}
                        Transfer
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default function AccountsPage() {
    const { data: summaryData, isLoading, error, refetch } = useAccountsSummary();
    const { data: transactionsData } = useCashboxTransactions();

    const summary = summaryData?.data || { cashbox: { balance: 0 }, bank: { balance: 0 }, total: 0 };
    // Extract transactions and ensure it's always an array
    const transactionsRaw = transactionsData?.data || transactionsData || [];
    const transactions = Array.isArray(transactionsRaw) ? transactionsRaw : [];

    if (isLoading) {
        return <LoadingState message="Loading accounts..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load accounts"
                message="Could not fetch account data"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Accounts</h1>
                    <p className="text-muted-foreground">Treasury management</p>
                </div>
                <TransferDialog />
            </div>

            {/* Balance Cards */}
            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    title="Cashbox"
                    value={formatMoney(summary.cashbox?.balance || 0)}
                    icon={<Wallet className="h-5 w-5" />}
                    className="bg-green-50 border-green-200"
                />
                <StatCard
                    title="Bank"
                    value={formatMoney(summary.bank?.balance || 0)}
                    icon={<Building2 className="h-5 w-5" />}
                    className="bg-blue-50 border-blue-200"
                />
                <StatCard
                    title="Total"
                    value={formatMoney(summary.total || 0)}
                    icon={<Wallet className="h-5 w-5" />}
                    className="bg-purple-50 border-purple-200"
                />
            </div>

            {/* Transactions */}
            <Card>
                <CardHeader>
                    <CardTitle>Recent Transactions</CardTitle>
                </CardHeader>
                <CardContent>
                    <Tabs defaultValue="all">
                        <TabsList className="mb-4">
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="cashbox">Cashbox</TabsTrigger>
                            <TabsTrigger value="bank">Bank</TabsTrigger>
                        </TabsList>
                        <TabsContent value="all" className="space-y-3">
                            {transactions.length === 0 ? (
                                <p className="text-center text-muted-foreground py-8">No transactions yet</p>
                            ) : (
                                transactions.slice(0, 10).map((t: { id: number; date: string; type: string; amount: number; notes?: string }) => (
                                    <div key={t.id} className="flex items-center justify-between py-3 border-b last:border-0">
                                        <div className="flex items-center gap-3">
                                            <div className={`h-8 w-8 rounded-full flex items-center justify-center ${t.type === 'deposit' ? 'bg-green-100 text-green-600' :
                                                t.type === 'withdraw' ? 'bg-red-100 text-red-600' :
                                                    'bg-blue-100 text-blue-600'
                                                }`}>
                                                {t.type === 'deposit' ? <TrendingUp className="h-4 w-4" /> :
                                                    t.type === 'withdraw' ? <TrendingDown className="h-4 w-4" /> :
                                                        <ArrowRightLeft className="h-4 w-4" />}
                                            </div>
                                            <div>
                                                <p className="font-medium text-sm">{t.notes || t.type}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateShort(t.date)}
                                                </p>
                                            </div>
                                        </div>
                                        <p className={`font-semibold money ${t.type === 'deposit' ? 'text-green-600' : 'text-red-600'
                                            }`}>
                                            {t.type === 'deposit' ? '+' : '-'}{formatMoney(t.amount)}
                                        </p>
                                    </div>
                                ))
                            )}
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    );
}
