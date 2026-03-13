import { router } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';
import { useImmer } from 'use-immer';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';
import { useDebounce } from '@/hooks';
import type { GradeLevel } from '@/modules/users/consts';
import type { StudentSearch } from '@/modules/users/types';

export const useStudentList = (initialFilters: StudentSearch) => {
    const [params, setParams] = useImmer<StudentSearch>(initialFilters);

    const fetchList = useCallback((currentParams: StudentSearch) => {
        router.get(listStudent(), currentParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    const debouncedSearch = useDebounce((keyword: string) => {
        setParams((draft) => {
            draft.filters.keyword = keyword;
            draft.page = 1; // Reset về trang 1 khi search
        });
    }, 500);

    useEffect(() => {
        fetchList(params);
    }, [fetchList, params]);

    const handleKeywordChange = (value: string) => {
        debouncedSearch(value);
    };

    const handleGradeChange = (grade: GradeLevel | 'all') => {
        setParams((draft) => {
            draft.filters.grade_level = grade === 'all' ? undefined : grade;
            draft.page = 1;
        });
    };

    const clearFilters = useCallback(() => {
        setParams((draft) => {
            draft.filters = {};
            draft.page = 1;
        });
    }, [setParams]);


    return {
        params,
        setParams,
        handleKeywordChange,
        handleGradeChange,
        clearFilters,
    };
};
