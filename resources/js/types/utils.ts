import type { JSX } from 'react';

export interface IMenu {
    title: string;
    is_menu?: boolean;
    url?: string;
    icon?: JSX.Element;
    active?: boolean;
    can_show?: boolean;
    items?: {
        title: string;
        url: string;
        active?: boolean;
        can_show?: boolean;
    }[];
}
export interface IBreadcrumbItem {
    title: string;
    href?: string;
}
