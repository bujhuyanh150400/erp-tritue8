"use client";

import { Filter, Search, X } from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardAction,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface FilterCardProps {
    children: React.ReactNode;
    onClear?: () => void;
    showClear?: boolean;
    title?: string;
    className?: string;
    onSubmit?: () => void;
    isLoading?: boolean;
}

export function FilterCard({
    children,
    onClear,
    showClear = false,
    title = 'Bộ lọc & Tìm kiếm',
    className,
    onSubmit, // Thêm prop onSubmit
    isLoading, // Thêm trạng thái loading cho nút submit
}: FilterCardProps) {
    return (
        <Card
            className={cn(
                'overflow-hidden border-none shadow-sm ring-1 ring-slate-200',
                className,
            )}
        >
            <CardHeader className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-x-2">
                    <Filter className="h-3.5 w-3.5 text-slate-600" />
                    {title}
                </CardTitle>
                <CardAction className="flex items-center gap-2">
                    {showClear && (
                        <Button
                            variant={'destructive'}
                            size="sm"
                            onClick={onClear}
                        >
                            <X className="mr-1.5 h-3.5 w-3.5" />
                            Xóa bộ lọc
                        </Button>
                    )}
                    <Button
                        size="sm"
                        onClick={onSubmit}
                        disabled={isLoading}
                    >
                        {isLoading ? (
                            <div className="mr-2 h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        ) : (
                            <Search className="mr-1.5 h-3.5 w-3.5" />
                        )}
                        Tìm kiếm
                    </Button>
                </CardAction>
            </CardHeader>

            <CardContent className="p-6">
                {/* Grid mặc định là 4 cột trên desktop, có thể tùy biến qua className ở con */}
                <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    {children}
                </div>
            </CardContent>
        </Card>
    );
}
