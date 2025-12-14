'use client';

import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';

interface PageHeaderProps {
    title: string;
    backHref?: string;
    actions?: React.ReactNode;
}

export function PageHeader({ title, backHref, actions }: PageHeaderProps) {
    const router = useRouter();

    return (
        <header className="bg-white border-b border-slate-200 sticky top-0 z-40">
            <div className="px-6 py-4 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    {backHref && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.push(backHref)}
                            className="text-slate-500 hover:text-slate-700"
                        >
                            <svg className="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                            رجوع
                        </Button>
                    )}
                    <h1 className="text-2xl font-bold text-slate-800">{title}</h1>
                </div>
                {actions && (
                    <div className="flex items-center gap-3">
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}

interface PageContainerProps {
    children: React.ReactNode;
}

export function PageContainer({ children }: PageContainerProps) {
    return (
        <div className="p-6">
            {children}
        </div>
    );
}
