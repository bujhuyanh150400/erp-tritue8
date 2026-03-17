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

/**
 * Tạo mật khẩu ngẫu nhiên có ít nhất 1 chữ hoa, 1 chữ thường, 1 số và 1 ký tự đặc biệt
 */
export const generateRandomPassword = (): string => {
    const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    const special = '!@%&';
    const all = upper + lower + numbers + special;

    let pwd = '';
    pwd += upper[Math.floor(Math.random() * upper.length)];
    pwd += lower[Math.floor(Math.random() * lower.length)];
    pwd += numbers[Math.floor(Math.random() * numbers.length)];
    pwd += special[Math.floor(Math.random() * special.length)];

    for (let i = 0; i < 6; i++) {
        pwd += all[Math.floor(Math.random() * all.length)];
    }
    return pwd
        .split('')
        .sort(() => 0.5 - Math.random())
        .join('');
};

/**
 * Kiểm tra xem URL có khớp với bất kỳ trong danh sách paths không
 * @param paths
 * @param url
 * @param exact
 */
export const isActiveUrl = (paths: string | string[], url: string, exact: boolean = false) => {
    // 1. Loại bỏ Query String (phần sau dấu ?) để so sánh chính xác pathname
    const currentPath = url.split('?')[0];

    const pathList = Array.isArray(paths) ? paths : [paths];

    return pathList.some((path) => {
        // Loại bỏ dấu gạch chéo cuối cùng của path để chuẩn hóa
        const normalizedPath =
            path.endsWith('/') && path !== '/' ? path.slice(0, -1) : path;

        if (exact) {
            return currentPath === normalizedPath;
        }

        // Nếu không yêu cầu exact, dùng startsWith
        // Bổ sung thêm dấu '/' để tránh trường hợp /admin/student active nhầm cho /admin/students-category
        return (
            currentPath === normalizedPath ||
            currentPath.startsWith(`${normalizedPath}/`)
        );
    });
}
