import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useImmer } from 'use-immer';
import { listSubject } from '@/actions/App/Http/Controllers/SubjectController';
import type { SubjectSearchRequest } from '@/modules/subject/types';


export const useListSubject = (initialSearch: SubjectSearchRequest) => {
    const [isLoading, setIsLoading] = useState(false); // Trạng thái loading

    // Dữ liệu gốc từ server truyền xuống
    const serverFilters = useMemo(() => {
        return initialSearch.filters && !Array.isArray(initialSearch.filters)
            ? initialSearch.filters
            : {};
    }, [initialSearch.filters]);

    const [filters, setFilters] = useImmer<
        Partial<SubjectSearchRequest['filters']>
    >({
        keyword: '',
        status: undefined,
    });

    // Đồng bộ lại local state nếu server thay đổi (VD: Nhấn back trang, click clear filters)
    useEffect(() => {
        setFilters(serverFilters);
    }, [serverFilters, setFilters]);

    // 3. Hàm cốt lõi: Đẩy tham số lên URL
    const triggerSearch = useCallback(
        (newFilters: Partial<SubjectSearchRequest['filters']>) => {
            const requestData: SubjectSearchRequest = {
                filters: newFilters,
                page: 1,
            };

            router.get(listSubject(), requestData, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['subjects'],
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
