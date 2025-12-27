'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import {
    Wallet,
    Building2,
    ArrowRight,
    ArrowLeft,
    TrendingUp,
    TrendingDown,
    Loader2,
    ArrowRightLeft,
    RefreshCw
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatCard } from '@/components/shared/stat-card';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { formatMoney, formatDateShort } from '@/lib/formatters';
import { useAccountsSummary, useCashboxTransactions, useTransfer } from '@/hooks/api/use-accounts';
import { cn } from '@/lib/utils';

// Quick amount buttons
const QUICK_AMOUNTS = [100, 500, 1000, 5000];

function TransferSection({
    cashboxBalance,
    bankBalance,
    cashboxId,
    bankId,
}: {
    cashboxBalance: number;
    bankBalance: number;
    cashboxId: number;
    bankId: number;
}) {
    const [amount, setAmount] = useState('');
    const [direction, setDirection] = useState<'to_bank' | 'to_cashbox'>('to_bank');
    const [notes, setNotes] = useState('');
    const transfer = useTransfer();

    const handleTransfer = async () => {
        const transferAmount = parseFloat(amount);
        if (!transferAmount || transferAmount <= 0) {
            toast.error('Please enter a valid amount');
            return;
        }

        const fromBalance = direction === 'to_bank' ? cashboxBalance : bankBalance;
        if (transferAmount > fromBalance) {
            toast.error(`Insufficient balance. Maximum: ${formatMoney(fromBalance)}`);
            return;
        }

        try {
            await transfer.mutateAsync({
                from_account_id: direction === 'to_bank' ? cashboxId : bankId,
                to_account_id: direction === 'to_bank' ? bankId : cashboxId,
                amount: transferAmount,
                notes: notes || `Transfer ${direction === 'to_bank' ? 'to Bank' : 'to Cashbox'}`,
            });
            toast.success('Transfer completed!');
            setAmount('');
            setNotes('');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Transfer failed');
        }
    };

    const handleQuickAmount = (value: number) => {
        setAmount(value.toString());
    };

    const toggleDirection = () => {
        setDirection(prev => prev === 'to_bank' ? 'to_cashbox' : 'to_bank');
    };

    return (
        <Card className="overflow-hidden">
            <CardHeader className="pb-2 bg-gradient-to-r from-primary/5 to-primary/10">
                <CardTitle className="flex items-center gap-2">
                    <ArrowRightLeft className="h-5 w-5" />
                    Quick Transfer
                </CardTitle>
                <CardDescription>Transfer funds between accounts instantly</CardDescription>
            </CardHeader>
            <CardContent className="pt-6">
                {/* Account Cards with Direction Control */}
                <div className="flex items-center gap-2 sm:gap-4 mb-6">
                    {/* From Account */}
                    <div
                        className={cn(
                            "flex-1 p-4 rounded-xl border-2 transition-all duration-300",
                            direction === 'to_bank'
                                ? "bg-green-50 border-green-300 shadow-md"
                                : "bg-blue-50 border-blue-300 shadow-md"
                        )}
                    >
                        <div className="flex items-center gap-2 mb-2">
                            {direction === 'to_bank' ? (
                                <Wallet className="h-5 w-5 text-green-600" />
                            ) : (
                                <Building2 className="h-5 w-5 text-blue-600" />
                            )}
                            <span className="text-sm font-medium text-muted-foreground">From</span>
                        </div>
                        <p className="font-bold text-lg">
                            {direction === 'to_bank' ? 'Cashbox' : 'Bank'}
                        </p>
                        <p className={cn(
                            "text-sm font-semibold money",
                            direction === 'to_bank' ? "text-green-600" : "text-blue-600"
                        )}>
                            {formatMoney(direction === 'to_bank' ? cashboxBalance : bankBalance)}
                        </p>
                    </div>

                    {/* Direction Toggle Button */}
                    <button
                        onClick={toggleDirection}
                        className="group relative flex-shrink-0 w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-primary text-primary-foreground shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110 active:scale-95"
                    >
                        <RefreshCw className="h-5 w-5 sm:h-6 sm:w-6 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 group-hover:rotate-180 transition-transform duration-500" />
                    </button>

                    {/* To Account */}
                    <div
                        className={cn(
                            "flex-1 p-4 rounded-xl border-2 transition-all duration-300",
                            direction === 'to_bank'
                                ? "bg-blue-50 border-blue-300 shadow-md"
                                : "bg-green-50 border-green-300 shadow-md"
                        )}
                    >
                        <div className="flex items-center gap-2 mb-2">
                            {direction === 'to_bank' ? (
                                <Building2 className="h-5 w-5 text-blue-600" />
                            ) : (
                                <Wallet className="h-5 w-5 text-green-600" />
                            )}
                            <span className="text-sm font-medium text-muted-foreground">To</span>
                        </div>
                        <p className="font-bold text-lg">
                            {direction === 'to_bank' ? 'Bank' : 'Cashbox'}
                        </p>
                        <p className={cn(
                            "text-sm font-semibold money",
                            direction === 'to_bank' ? "text-blue-600" : "text-green-600"
                        )}>
                            {formatMoney(direction === 'to_bank' ? bankBalance : cashboxBalance)}
                        </p>
                    </div>
                </div>

                {/* Arrow Indicator */}
                <div className="flex justify-center mb-4">
                    <div className={cn(
                        "flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-300",
                        "bg-muted"
                    )}>
                        {direction === 'to_bank' ? (
                            <>
                                <Wallet className="h-4 w-4 text-green-600" />
                                <ArrowRight className="h-4 w-4 animate-pulse" />
                                <Building2 className="h-4 w-4 text-blue-600" />
                            </>
                        ) : (
                            <>
                                <Building2 className="h-4 w-4 text-blue-600" />
                                <ArrowLeft className="h-4 w-4 animate-pulse" />
                                <Wallet className="h-4 w-4 text-green-600" />
                            </>
                        )}
                    </div>
                </div>

                {/* Quick Amount Buttons */}
                <div className="flex gap-2 mb-4 flex-wrap">
                    {QUICK_AMOUNTS.map((value) => (
                        <Button
                            key={value}
                            variant={amount === value.toString() ? "default" : "outline"}
                            size="sm"
                            onClick={() => handleQuickAmount(value)}
                            className="flex-1 min-w-[70px]"
                        >
                            {value.toLocaleString()}
                        </Button>
                    ))}
                </div>

                {/* Amount Input */}
                <div className="space-y-4">
                    <div className="relative">
                        <Input
                            type="number"
                            inputMode="decimal"
                            placeholder="Enter amount..."
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            className="text-lg h-14 pr-16 text-center font-semibold"
                        />
                        <span className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground font-medium">
                            QAR
                        </span>
                    </div>

                    {/* Notes Input */}
                    <Input
                        placeholder="Add a note (optional)..."
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        className="h-12"
                    />

                    {/* Transfer Button */}
                    <Button
                        onClick={handleTransfer}
                        disabled={transfer.isPending || !amount}
                        className="w-full h-14 text-lg font-semibold"
                        size="lg"
                    >
                        {transfer.isPending ? (
                            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                        ) : (
                            <ArrowRightLeft className="mr-2 h-5 w-5" />
                        )}
                        Transfer {amount ? formatMoney(parseFloat(amount) || 0) : ''}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function AccountsPage() {
    const { data: summaryData, isLoading, error, refetch } = useAccountsSummary();
    const { data: transactionsData } = useCashboxTransactions();

    // Extract account data with dynamic IDs (fallback to 1/2 for safety)
    const rawSummary = summaryData?.data as {
        cashbox?: { id?: number; balance: number };
        bank?: { id?: number; balance: number };
        total?: number
    } | undefined;
    const summary = {
        cashbox: { id: rawSummary?.cashbox?.id || 1, balance: rawSummary?.cashbox?.balance || 0 },
        bank: { id: rawSummary?.bank?.id || 2, balance: rawSummary?.bank?.balance || 0 },
        total: rawSummary?.total || 0,
    };
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
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold">Accounts</h1>
                <p className="text-muted-foreground">Treasury management & transfers</p>
            </div>

            {/* Balance Cards */}
            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    title="Cashbox"
                    value={formatMoney(summary.cashbox?.balance || 0)}
                    icon={<Wallet className="h-5 w-5" />}
                    className="bg-gradient-to-br from-green-50 to-green-100 border-green-200"
                />
                <StatCard
                    title="Bank"
                    value={formatMoney(summary.bank?.balance || 0)}
                    icon={<Building2 className="h-5 w-5" />}
                    className="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200"
                />
                <StatCard
                    title="Total"
                    value={formatMoney(summary.total || 0)}
                    icon={<Wallet className="h-5 w-5" />}
                    className="bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200"
                />
            </div>

            {/* Transfer Section */}
            <TransferSection
                cashboxBalance={summary.cashbox.balance}
                bankBalance={summary.bank.balance}
                cashboxId={summary.cashbox.id}
                bankId={summary.bank.id}
            />

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
                                            <div className={`h-10 w-10 rounded-full flex items-center justify-center ${t.type === 'deposit' ? 'bg-green-100 text-green-600' :
                                                t.type === 'withdraw' ? 'bg-red-100 text-red-600' :
                                                    'bg-blue-100 text-blue-600'
                                                }`}>
                                                {t.type === 'deposit' ? <TrendingUp className="h-5 w-5" /> :
                                                    t.type === 'withdraw' ? <TrendingDown className="h-5 w-5" /> :
                                                        <ArrowRightLeft className="h-5 w-5" />}
                                            </div>
                                            <div>
                                                <p className="font-medium">{t.notes || t.type}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDateShort(t.date)}
                                                </p>
                                            </div>
                                        </div>
                                        <p className={`font-semibold text-lg money ${t.type === 'deposit' ? 'text-green-600' : 'text-red-600'
                                            }`}>
                                            {t.type === 'deposit' ? '+' : '-'}{formatMoney(t.amount)}
                                        </p>
                                    </div>
                                ))
                            )}
                        </TabsContent>
                        <TabsContent value="cashbox" className="py-8 text-center text-muted-foreground">
                            Filter coming soon...
                        </TabsContent>
                        <TabsContent value="bank" className="py-8 text-center text-muted-foreground">
                            Filter coming soon...
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    );
}
