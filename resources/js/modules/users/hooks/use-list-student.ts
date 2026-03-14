import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useImmer } from 'use-immer';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';
import type { StudentSearchRequest } from '@/modules/users/types';


export const useStudentList = (initialSearch: StudentSearchRequest) => {
    const [isLoading, setIsLoading] = useState(false); // Trạng thái loading

    // Dữ liệu gốc từ server truyền xuống
    const serverFilters = useMemo(() => {
        return initialSearch.filters && !Array.isArray(initialSearch.filters)
            ? initialSearch.filters
            : {};
    }, [initialSearch.filters]);

    const [filters, setFilters] = useImmer<
        Partial<StudentSearchRequest['filters']>
    >({
        keyword: '',
        grade_level: undefined,
    });

    // Đồng bộ lại local state nếu server thay đổi (VD: Nhấn back trang, click clear filters)
    useEffect(() => {
        setFilters(serverFilters);
    }, [serverFilters, setFilters]);

    // 3. Hàm cốt lõi: Đẩy tham số lên URL
    const triggerSearch = useCallback(
        (newFilters: Partial<StudentSearchRequest['filters']>) => {
            const requestData: StudentSearchRequest = {
                filters: newFilters,
                page: 1,
            };

            router.get(listStudent(), requestData, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['students'],
                onBefore: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            });
        },
        [],
    );

    const clearFilters = useCallback(() => {
        setFilters({});
        triggerSearch({});
    }, [setFilters, triggerSearch]);

    return {
        filters,
        setFilters,
        clearFilters,
        triggerSearch,
        isLoading,
    };
};
