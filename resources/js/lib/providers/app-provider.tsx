"use client";

import type { ReactNode } from 'react';
import { QueryProvider } from '@/lib/providers/query-client';

export const AppProvider = ({ children }: { children: ReactNode }) => {
    return (
        <QueryProvider>
            {children}
        </QueryProvider>
    )
};
