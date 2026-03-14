import type { JSX } from 'react';

export interface IMenu {
    title: string;
    is_menu?: boolean;
    url?: string;
    icon?: JSX.Element;
    active?: boolean;
    items?: {
        title: string;
        url: string;
        active?: boolean;
    }[];
}
export interface IBreadcrumbItem {
    title: string;
    href?: string;
}

export type ToastData = {
    message: string;
    title: string;
}

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
    page: number | null;
};

export type LaravelPaginator<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        next: string | null;
        prev: string | null;
    };
    meta: {
        links: PaginatorLink[];
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
};

export type ResponseDataSuccessType<T> = {
    message: string;
    data: T;
};

export type ResponseSuccessType = {
    message: string;
};

export type BaseSearchRequest<TFilter> = {
    filters: TFilter;
    sort_by?: string;
    direction?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
};
