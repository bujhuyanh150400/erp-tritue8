import type { ReactNode } from 'react';
import React from 'react';
import { cn } from '@/lib/utils';

interface PageHeaderProps {
    title: string;
    description?: string;
    children?: ReactNode; // Đây là nơi chứa các Button hành động
    className?: string;
}

export function PageHeader({
    title,
    description,
    children,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            {/* Left: Info */}
            <div className="space-y-1">
                <h2 className="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                    {title}
                </h2>
                {description && (
                    <p className="max-w-125 text-sm text-slate-500">
                        {description}
                    </p>
                )}
            </div>

            {/* Right: Actions */}
            <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                {children}
            </div>
        </div>
    );
}
