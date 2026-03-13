import type { InertiaLinkProps } from '@inertiajs/react';
import { LayoutDashboard, Users } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/DashboardController';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';

import type { IMenu, User } from '@/lib/types';
import { UserRole } from '@/lib/types';
import { resolveUrl } from '@/lib/utils';

/**
 * Lấy menu theo role của user
 * @param user
 * @param url
 */
export const useMenu: (user: User, url: string) => IMenu[] = (user: User, url: string) => {
    const isActive = useCallback(
        (href: NonNullable<InertiaLinkProps['href']>) => {
            return url.startsWith(resolveUrl(href));
        },
        [url],
    );
    return useMemo(() => {
        switch (user.role) {
            case UserRole.Admin:
               return [
                   {
                       title: 'Bảng điều khiển',
                       url: dashboardIndex().url,
                       icon: <LayoutDashboard />,
                       is_menu: true,
                       active: isActive(dashboardIndex()),
                   },
                   {
                       title: 'Người dùng',
                       is_menu: false,
                   },
                   {
                       title: 'Học sinh',
                       url: listStudent().url,
                       icon: <Users />,
                       is_menu: true,
                   },
               ];
            default:
                return [];
        }
    }, [isActive, user.role]);
};
