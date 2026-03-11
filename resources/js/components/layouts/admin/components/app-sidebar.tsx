import { Link } from '@inertiajs/react';
import * as React from 'react';
import Logo from '@/assets/images/logo.png';
import { NavMain } from '@/components/layouts/admin/components/nav-main';
import { NavUser } from '@/components/layouts/admin/components/nav-user';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import type { User } from '@/lib/types';
import { useMenu } from '@/modules/application/hooks';
import { dashboard } from '@/routes';


export function AppSidebar({ user, url, appName }: { user: User, url: string, appName: string }) {
    const menus = useMenu(user, url);
    return (
        <Sidebar collapsible="icon">
            {/* Header */}
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {/* Dùng asChild để bọc Link của Inertia mà không làm hỏng CSS */}
                            <Link href={dashboard()}>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg">
                                    <Avatar className="size-8 rounded-lg">
                                        <AvatarImage src={Logo} alt={appName} />
                                    </Avatar>
                                </div>

                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {appName}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Quản lý trường học
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain menus={menus} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
