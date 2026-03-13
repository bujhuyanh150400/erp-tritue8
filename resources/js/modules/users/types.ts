import type  { BaseSearchRequest, LaravelPaginator, UserRole } from '@/lib/types';
import type { Gender, GradeLevel } from '@/modules/users/consts';

export type StudentList = {
    id: string;
    user:{
        id: string;
        is_active: boolean;
        role: UserRole
    }
    full_name: string;
    dob: string;
    gender: Gender;
    grade_level: GradeLevel;
    parent_name: string;
    parent_phone: string;
    address: string;
    note: string | null;
};

export type StudentSearch = BaseSearchRequest<{
    keyword?: string;
    grade_level?: GradeLevel;
}>;


export type StudentListPaginator = LaravelPaginator<StudentList>
