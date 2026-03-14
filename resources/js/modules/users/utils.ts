import { UserRole } from '@/lib/types';
import { mapEnumToOptions } from '@/lib/utils';
import { Gender, GradeLevel } from '@/modules/users/consts';

export const getGenderLabel = (gender: Gender) => {
    switch (gender) {
        case Gender.Male:
            return 'Nam';
        case Gender.Female:
            return 'Nữ';
        case Gender.Other:
        default:
            return 'Khác';
    }
};

export const getGenderClassStyleBadge = (gender: Gender) => {
    switch (gender) {
        case Gender.Male:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-blue-50 text-blue-700 border-blue-200';
        case Gender.Female:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-pink-50 text-pink-700 border-pink-200';
        case Gender.Other:
        default:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-gray-50 text-gray-700 border-gray-200';
    }
};

export const getGradeLevelLabel = (gradeLevel: number) => {
    switch (gradeLevel) {
        case GradeLevel.Grade0:
            return 'Tiền tiểu học';
        case GradeLevel.Grade1:
            return 'Lớp 1';
        case GradeLevel.Grade2:
            return 'Lớp 2';
        case GradeLevel.Grade3:
            return 'Lớp 3';
        case GradeLevel.Grade4:
            return 'Lớp 4';
        case GradeLevel.Grade5:
            return 'Lớp 5';
        case GradeLevel.Grade6:
            return 'Lớp 6';
        case GradeLevel.Grade7:
            return 'Lớp 7';
        case GradeLevel.Grade8:
            return 'Lớp 8';
        case GradeLevel.Grade9:
            return 'Lớp 9';
        case GradeLevel.Grade10:
            return 'Lớp 10';
        case GradeLevel.Grade11:
            return 'Lớp 11';
        case GradeLevel.Grade12:
            return 'Lớp 12';
        default:
            return 'Không xác định';
    }
};

const ROLE_PREFIX: Record<UserRole, string> = {
    [UserRole.Admin]: 'ad',
    [UserRole.Teacher]: 'gv',
    [UserRole.Staff]: 'nv',
    [UserRole.Student]: 'hs',
};

export const generateUsername = (fullName: string, role: UserRole): string => {
    if (!fullName) return '';

    const normalized = fullName
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Xóa dấu
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9\s]/g, '') // Xóa ký tự đặc biệt
        .trim()
        .replace(/\s+/g, ''); // Xóa khoảng trắng

    const prefix = ROLE_PREFIX[role] || 'user';

    return `${prefix}_${normalized}`;
};
