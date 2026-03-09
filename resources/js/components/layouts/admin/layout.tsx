'use client';

import type { FC, ReactNode } from 'react';
import { AppHeader, AppSidebar } from '@/components/layouts/admin/components';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { IBreadcrumbItem } from '@/types';

type Props = {
    children: ReactNode;
    breadcrumbs?: IBreadcrumbItem[];
};

const Layout: FC<Props> = ({ children, breadcrumbs }) => {
    return (
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar />
                <SidebarInset>
                    <AppHeader breadcrumbs={breadcrumbs} />
                    <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
};
export default Layout;
