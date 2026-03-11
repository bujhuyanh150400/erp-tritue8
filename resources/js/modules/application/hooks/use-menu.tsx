import type { InertiaLinkProps } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import type { IMenu, User } from '@/lib/types';
import { UserRole } from '@/lib/types';
import { resolveUrl } from '@/lib/utils';
import { dashboard } from '@/routes';

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
                       title: "Bảng điều khiển",
                       url: dashboard().url,
                       icon: <LayoutDashboard />,
                       is_menu: true,
                       active: isActive(dashboard()),
                   },
               ];
            default:
                return [];
        }
    }, [isActive, user.role]);
};
