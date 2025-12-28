'use client';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface DateRangePickerProps {
    dateFrom: string;
    dateTo: string;
    onDateFromChange: (date: string) => void;
    onDateToChange: (date: string) => void;
}

type PresetKey = 'today' | 'yesterday' | 'this_week' | 'last_week' | 'this_month' | 'last_month' | 'this_year' | 'custom';

const getPresetDates = (preset: PresetKey): { from: string; to: string } => {
    const today = new Date();
    // Use local timezone to avoid off-by-one date bugs
    const formatDate = (d: Date) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    switch (preset) {
        case 'today':
            return { from: formatDate(today), to: formatDate(today) };
        case 'yesterday': {
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            return { from: formatDate(yesterday), to: formatDate(yesterday) };
        }
        case 'this_week': {
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            return { from: formatDate(startOfWeek), to: formatDate(today) };
        }
        case 'last_week': {
            const endOfLastWeek = new Date(today);
            endOfLastWeek.setDate(today.getDate() - today.getDay() - 1);
            const startOfLastWeek = new Date(endOfLastWeek);
            startOfLastWeek.setDate(endOfLastWeek.getDate() - 6);
            return { from: formatDate(startOfLastWeek), to: formatDate(endOfLastWeek) };
        }
        case 'this_month': {
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            return { from: formatDate(startOfMonth), to: formatDate(today) };
        }
        case 'last_month': {
            const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            return { from: formatDate(startOfLastMonth), to: formatDate(endOfLastMonth) };
        }
        case 'this_year': {
            const startOfYear = new Date(today.getFullYear(), 0, 1);
            return { from: formatDate(startOfYear), to: formatDate(today) };
        }
        default:
            return { from: '', to: '' };
    }
};

export function DateRangePicker({
    dateFrom,
    dateTo,
    onDateFromChange,
    onDateToChange,
}: DateRangePickerProps) {
    const handlePresetChange = (preset: string) => {
        if (preset === 'custom') {
            return;
        }
        const dates = getPresetDates(preset as PresetKey);
        onDateFromChange(dates.from);
        onDateToChange(dates.to);
    };

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base">Date Range</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex flex-wrap gap-4 items-end">
                    {/* Quick Presets */}
                    <div className="space-y-2">
                        <Label>Quick Select</Label>
                        <Select onValueChange={handlePresetChange}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="Choose..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="today">Today</SelectItem>
                                <SelectItem value="yesterday">Yesterday</SelectItem>
                                <SelectItem value="this_week">This Week</SelectItem>
                                <SelectItem value="last_week">Last Week</SelectItem>
                                <SelectItem value="this_month">This Month</SelectItem>
                                <SelectItem value="last_month">Last Month</SelectItem>
                                <SelectItem value="this_year">This Year</SelectItem>
                                <SelectItem value="custom">Custom</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* From Date */}
                    <div className="space-y-2">
                        <Label>From</Label>
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => onDateFromChange(e.target.value)}
                            className="w-[160px]"
                        />
                    </div>

                    {/* To Date */}
                    <div className="space-y-2">
                        <Label>To</Label>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(e) => onDateToChange(e.target.value)}
                            className="w-[160px]"
                        />
                    </div>

                    {/* Clear Button */}
                    {(dateFrom || dateTo) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                onDateFromChange('');
                                onDateToChange('');
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
