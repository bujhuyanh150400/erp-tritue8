import type { ActiveStatus } from '@/lib/consts';
import type { BaseSearchRequest, LaravelPaginator } from '@/lib/types';

export type SubjectItem = {
    id: string;
    name: string;
    description: string | null;
    classes_count: number;
    is_active: boolean;
};

export type SubjectListPaginator = LaravelPaginator<SubjectItem>;

export type SubjectSearchRequest = BaseSearchRequest<{
    keyword?: string;
    status?: ActiveStatus;
}>;


export type SubjectForm = {
    name: string;
    description: string;
    is_active: boolean;

}
