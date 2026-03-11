'use client';

import { usePage } from '@inertiajs/react';
import type { FC, ReactNode } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AppHeader, AppSidebar } from '@/components/layouts/admin/components';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { IBreadcrumbItem, ToastData } from '@/lib/types';

type Props = {
    children: ReactNode;
    breadcrumbs?: IBreadcrumbItem[];
};

const Layout: FC<Props> = ({ children, breadcrumbs }) => {
    const { props, url } = usePage();
    const { user } = props.auth;
    const { flash } = props;

    // Hiển thị toast khi có flash message
    useEffect(() => {
        const toastTypes = ['success', 'error', 'info', 'warning'] as const;
        toastTypes.forEach((type) => {
            const data = flash?.[type];
            if (data) {
                toast[type](data.title || 'Thông báo', {
                    description: data.message,
                    duration: 3000,
                });
            }
        });
    }, [flash]);

    return (
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar user={user} url={url} appName={props.name} />
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
