import { useForm } from '@inertiajs/react';
import type { ChangeEvent, SubmitEvent } from 'react';
import { useCallback } from 'react';
import {
    create,
    update,
} from '@/actions/App/Http/Controllers/StudentController';
import { useDebounce } from '@/hooks';
import { UserRole } from '@/lib/types';
import { generateRandomPassword } from '@/lib/utils'; // Giả định action của bạn
import { Gender, GradeLevel } from '@/modules/users/consts';
import type { StudentForm, StudentItem } from '@/modules/users/types';
import {
    generateUsername,
} from '@/modules/users/utils';

export const useStudentForm = (student?: StudentItem) => {

    const isUpdate = !!student;
    const form = useForm<StudentForm>({
        full_name: student?.full_name || '',
        user_name: student?.user_name || '', // Lấy từ resource
        password: '', // Luôn để trống khi update để bảo mật
        dob: student?.dob || '',
        gender: student?.gender ?? Gender.Other,
        grade_level: student?.grade_level ?? GradeLevel.Grade0,
        parent_name: student?.parent_name || '',
        parent_phone: student?.parent_phone || '',
        address: student?.address || '',
        note: student?.note || '',
    });

    const { setData, post,put, reset, clearErrors } = form;

    // 1. Logic Debounce Username
    const debounceGenerateUsername = useDebounce((name: string) => {
        const newUsername = generateUsername(name, UserRole.Student);
        setData('user_name', newUsername);
    }, 500);

    const handleFullNameChange = useCallback(
        (e: ChangeEvent<HTMLInputElement>) => {
            const value = e.target.value;
            setData('full_name', value);

            if (!isUpdate) {
                if (value.trim()) {
                    debounceGenerateUsername(value);
                } else {
                    setData('user_name', '');
                }
            }
        },
        [setData, debounceGenerateUsername, isUpdate],
    );

    const handleRandomPassword = useCallback(() => {
        setData('password', generateRandomPassword());
    }, [setData]);

    const handleReset = useCallback(() => {
        reset();
        clearErrors();
    }, [reset, clearErrors]);

    const handleSubmit = useCallback(
        (e: SubmitEvent<HTMLFormElement>) => {
            e.preventDefault();
            if (isUpdate && student) {
                put(update({id: student?.user_id}).url, {
                    onSuccess: () => {
                        handleReset();
                    },
                    preserveScroll: true,
                });
            } else {
                post(create().url, {
                    onSuccess: () => {
                        handleReset();
                    },
                    preserveScroll: true,
                });
            }
        },
        [isUpdate, student, put, handleReset, post],
    );



    return {
        form,
        isUpdate,
        handleFullNameChange,
        handleRandomPassword,
        handleSubmit,
        handleReset,
    };
};
