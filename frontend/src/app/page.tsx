'use client';

import { useAuth } from '@/hooks/useAuth';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { DashboardStats } from '@/types';

export default function DashboardPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loadingStats, setLoadingStats] = useState(true);

  useEffect(() => {
    if (!loading && !user) {
      router.push('/login');
    }
  }, [user, loading, router]);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const data = await api.getDashboard();
        setStats(data);
      } catch (err) {
        console.error('Failed to fetch stats:', err);
      } finally {
        setLoadingStats(false);
      }
    };

    if (user) {
      fetchStats();
    }
  }, [user]);

  if (loading || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  const StatCard = ({ title, value, icon, color }: { title: string; value: string | number; icon: string; color: string }) => (
    <div className={`bg-gradient-to-br ${color} rounded-2xl p-6 text-white shadow-xl`}>
      <div className="flex justify-between items-start">
        <div>
          <p className="text-white/70 text-sm mb-1">{title}</p>
          <p className="text-3xl font-bold">{value}</p>
        </div>
        <span className="text-4xl">{icon}</span>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
      {/* Header */}
      <header className="bg-white/5 backdrop-blur-xl border-b border-white/10">
        <div className="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
          <h1 className="text-2xl font-bold text-white">نظام المخزون</h1>
          <div className="flex items-center gap-4">
            <span className="text-slate-400">مرحباً، {user.name}</span>
            <button
              onClick={logout}
              className="px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-colors"
            >
              خروج
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-6 py-8">
        <h2 className="text-xl font-semibold text-white mb-6">لوحة التحكم</h2>

        {/* Stats Grid */}
        {loadingStats ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
              <div key={i} className="bg-white/5 rounded-2xl p-6 h-32 animate-pulse"></div>
            ))}
          </div>
        ) : stats && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard
              title="عدد العملاء"
              value={stats.customers_count}
              icon="👥"
              color="from-blue-600 to-blue-500"
            />
            <StatCard
              title="عدد الموردين"
              value={stats.suppliers_count || 0}
              icon="🏭"
              color="from-slate-600 to-slate-500"
            />
            <StatCard
              title="إجمالي المديونية"
              value={`${stats.total_receivables.toLocaleString()} ج.م`}
              icon="💰"
              color="from-amber-600 to-amber-500"
            />
            <StatCard
              title="إجمالي المستحقات للموردين"
              value={`${(stats.total_payables || 0).toLocaleString()} ج.م`}
              icon="💳"
              color="from-red-600 to-red-500"
            />
            <StatCard
              title="الشحنات المفتوحة"
              value={stats.open_shipments}
              icon="📦"
              color="from-emerald-600 to-emerald-500"
            />
            <StatCard
              title="مبيعات اليوم"
              value={`${stats.today_sales.toLocaleString()} ج.م`}
              icon="📈"
              color="from-purple-600 to-purple-500"
            />
            <StatCard
              title="تحصيلات اليوم"
              value={`${stats.today_collections.toLocaleString()} ج.م`}
              icon="💵"
              color="from-cyan-600 to-cyan-500"
            />
            <StatCard
              title="مصروفات اليوم"
              value={`${(stats.today_expenses || 0).toLocaleString()} ج.م`}
              icon="📉"
              color="from-pink-600 to-pink-500"
            />
          </div>
        )}

        {/* Quick Actions */}
        <div className="mt-12">
          <h3 className="text-lg font-semibold text-white mb-4">إجراءات سريعة</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {[
              { label: 'فاتورة جديدة', icon: '📝', href: '/invoices/new' },
              { label: 'تحصيل جديد', icon: '💳', href: '/collections/new' },
              { label: 'شحنة جديدة', icon: '🚚', href: '/shipments/new' },
              { label: 'مصروف جديد', icon: '💸', href: '/expenses/new' },
              { label: 'التقارير', icon: '📊', href: '/reports' },
              { label: 'العملاء', icon: '👥', href: '/customers' },
            ].map((action) => (
              <button
                key={action.label}
                onClick={() => router.push(action.href)}
                className="bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl p-4 text-center transition-all"
              >
                <span className="text-3xl block mb-2">{action.icon}</span>
                <span className="text-white text-sm">{action.label}</span>
              </button>
            ))}
          </div>
        </div>
      </main>
    </div>
  );
}
