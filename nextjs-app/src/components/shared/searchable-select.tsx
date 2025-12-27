'use client';

import * as React from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Search } from 'lucide-react';

export interface FilterableSelectOption {
    value: string;
    label: string;
    description?: string;
}

interface FilterableSelectProps {
    options: FilterableSelectOption[];
    value: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    className?: string;
    /** Threshold to enable search (default: 10) */
    searchThreshold?: number;
}

/**
 * FilterableSelect - Select with built-in search for large lists
 * 
 * Automatically shows search input when options exceed threshold.
 * Filters options as you type, caps visible results at 50.
 */
export function FilterableSelect({
    options,
    value,
    onValueChange,
    placeholder = 'Select...',
    searchPlaceholder = 'Type to filter...',
    disabled = false,
    className,
    searchThreshold = 10,
}: FilterableSelectProps) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const showSearch = options.length > searchThreshold;

    // Filter options based on search query (case-insensitive)
    const filteredOptions = React.useMemo(() => {
        if (!searchQuery) return options.slice(0, 50);

        const query = searchQuery.toLowerCase();
        return options
            .filter(opt =>
                opt.label.toLowerCase().includes(query) ||
                opt.description?.toLowerCase().includes(query)
            )
            .slice(0, 50);
    }, [options, searchQuery]);

    // Get selected option label for display
    const selectedOption = options.find(opt => opt.value === value);

    return (
        <div className={className}>
            {showSearch && (
                <div className="relative mb-2">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder={searchPlaceholder}
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-9"
                        disabled={disabled}
                    />
                </div>
            )}
            <Select value={value} onValueChange={onValueChange} disabled={disabled}>
                <SelectTrigger className="touch-target">
                    <SelectValue placeholder={placeholder}>
                        {selectedOption?.label}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {filteredOptions.length === 0 ? (
                        <div className="py-6 text-center text-sm text-muted-foreground">
                            No results found
                        </div>
                    ) : (
                        filteredOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.description ? (
                                    <div className="flex flex-col">
                                        <span>{option.label}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {option.description}
                                        </span>
                                    </div>
                                ) : (
                                    option.label
                                )}
                            </SelectItem>
                        ))
                    )}
                </SelectContent>
            </Select>
        </div>
    );
}
