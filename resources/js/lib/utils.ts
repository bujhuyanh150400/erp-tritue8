import type { InertiaLinkProps } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Trộn các class name lại với nhau, loại bỏ trùng lặp và áp dụng tailwind-merge
 * @param inputs
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Chuyển đổi URL có thể là string hoặc object thành string
 * @param url
 */
export function resolveUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Chuyển đổi chuỗi ngày tháng năm thành định dạng ngày/tháng/năm
 * @param dateString
 */
export const formatDate = (dateString: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

/**
 * Chuyển đổi enum thành mảng option cho Select component
 * @param enumObj
 * @param labelResolver
 * @param allLabel
 */
export const mapEnumToOptions = <T extends string | number>(
    enumObj: Record<string, T | string>,
    labelResolver: (value: T) => string,
    allLabel?: string,
) => {
    const options = Object.values(enumObj)
        /**
         * CHỖ THAY ĐỔI QUAN TRỌNG:
         * Nếu là Enum số, Object.values sẽ chứa cả string (key) và number (value).
         * Ta chỉ lọc lấy phần 'number' để khớp với hàm labelResolver của bạn.
         */
        .filter((value): value is T => typeof value === 'number')
        .map((value) => ({
            value: value.toString(),
            label: labelResolver(value),
        }));

    if (allLabel) {
        return [{ value: 'all', label: allLabel }, ...options];
    }

    return options;
};

/**
 * Kiểm tra xem có ít nhất một giá trị khác rỗng trong filters hay không
 * @param filters
 */
export const isFilterActive = (filters: Record<string, any>) => {
    return Object.values(filters).some(
        (v) => v !== undefined && v !== null && v !== '',
    );
};
