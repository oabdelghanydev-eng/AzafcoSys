'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { Globe } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const locales = [
    { code: 'en', label: 'English', dir: 'ltr' },
    { code: 'ar', label: 'العربية', dir: 'rtl' },
];

// Helper to set cookie - defined outside component to avoid React Compiler issues
function setCookie(name: string, value: string) {
    if (typeof document !== 'undefined') {
        document.cookie = `${name}=${value}; path=/; max-age=31536000`;
    }
}

export function LanguageToggle() {
    const router = useRouter();
    const [isPending, startTransition] = useTransition();

    // Get initial locale from cookie using lazy initializer
    const getInitialLocale = () => {
        if (typeof document !== 'undefined') {
            const cookieLocale = document.cookie.split('; ').find(row => row.startsWith('locale='))?.split('=')[1];
            return cookieLocale || 'en';
        }
        return 'en';
    };

    const [currentLocale, setCurrentLocale] = useState(getInitialLocale);

    // Sync DOM attributes when locale changes - moved from effect to render for React Compiler
    if (typeof document !== 'undefined') {
        const localeData = locales.find(l => l.code === currentLocale);
        if (localeData) {
            document.documentElement.dir = localeData.dir;
            document.documentElement.lang = currentLocale;
        }
    }

    const handleLocaleChange = (locale: string) => {
        setCookie('locale', locale);
        setCurrentLocale(locale);

        // Refresh the page to apply new locale
        startTransition(() => {
            router.refresh();
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" disabled={isPending}>
                    <Globe className="h-5 w-5" />
                    <span className="sr-only">Toggle language</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {locales.map((locale) => (
                    <DropdownMenuItem
                        key={locale.code}
                        onClick={() => handleLocaleChange(locale.code)}
                        className={currentLocale === locale.code ? 'bg-muted' : ''}
                    >
                        {locale.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
