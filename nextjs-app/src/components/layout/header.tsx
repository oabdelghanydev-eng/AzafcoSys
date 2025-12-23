'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Menu, LogOut, Calendar, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useAuthStore } from '@/stores/auth-store';
import { useUIStore } from '@/stores/ui-store';
import { useCurrentDay } from '@/hooks/api/use-daily-report';
import { api } from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { formatDateShort } from '@/lib/formatters';

export function Header() {
    const router = useRouter();
    const { user, logout: logoutStore } = useAuthStore();
    const { workingDate, setWorkingDate, toggleMobileDrawer } = useUIStore();

    // Fetch current open day from API and sync with store
    const { data: currentDay, isLoading: dayLoading } = useCurrentDay();

    // Sync the current day with the UI store whenever it changes
    useEffect(() => {
        if (currentDay?.date && currentDay?.status === 'open') {
            setWorkingDate(currentDay.date);
        } else if (currentDay === null || (currentDay && currentDay.status !== 'open')) {
            // Only clear if we got a definitive response (not loading)
            if (!dayLoading) {
                setWorkingDate(null);
            }
        }
    }, [currentDay, dayLoading, setWorkingDate]);

    const handleLogout = async () => {
        try {
            await api.post(endpoints.auth.logout);
            logoutStore();
            toast.success('Logged out successfully');
            router.push('/login');
        } catch (_error) {
            // Still logout locally even if API fails
            logoutStore();
            router.push('/login');
        }
    };

    return (
        <header className="h-16 border-b bg-background flex items-center justify-between px-4 lg:px-6">
            {/* Mobile menu button */}
            <div className="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    className="lg:hidden"
                    onClick={toggleMobileDrawer}
                >
                    <Menu className="h-5 w-5" />
                </Button>

                {/* Working Date - Show loading state briefly */}
                {dayLoading ? (
                    <div className="hidden sm:flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Checking...</span>
                    </div>
                ) : workingDate ? (
                    <div className="hidden sm:flex items-center gap-2 text-sm">
                        <Calendar className="h-4 w-4 text-green-600" />
                        <span className="text-muted-foreground">Working Day:</span>
                        <span className="font-medium text-green-600">{formatDateShort(workingDate)}</span>
                    </div>
                ) : (
                    <div className="hidden sm:flex items-center gap-2 text-sm text-orange-600">
                        <Calendar className="h-4 w-4" />
                        <span>No day is open</span>
                    </div>
                )}
            </div>

            {/* Right side */}
            <div className="flex items-center gap-2">
                {/* User Menu */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="relative h-10 w-10 rounded-full">
                            <Avatar className="h-10 w-10">
                                <AvatarFallback className="bg-primary text-primary-foreground">
                                    {user?.name?.charAt(0).toUpperCase() || 'U'}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <div className="flex items-center justify-start gap-2 p-2">
                            <div className="flex flex-col space-y-0.5">
                                <p className="text-sm font-medium">{user?.name}</p>
                                <p className="text-xs text-muted-foreground">{user?.email}</p>
                            </div>
                        </div>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={handleLogout} className="text-destructive cursor-pointer">
                            <LogOut className="mr-2 h-4 w-4" />
                            Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
