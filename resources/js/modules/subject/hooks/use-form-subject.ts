import { useForm } from '@inertiajs/react';
import { useCallback } from 'react';
import type {  SubmitEvent } from 'react';
import {
    update,
    create,
} from '@/actions/App/Http/Controllers/SubjectController';
import type { SubjectItem, SubjectForm } from '@/modules/subject/types';

export const useFormSubject = (subject?: SubjectItem) => {
    const isUpdate = !!subject;

    // Khởi tạo form với default is_active = true
    const form = useForm<SubjectForm>({
        name: subject?.name || '',
        description: subject?.description || '',
        is_active: subject?.is_active || true,
    });

    const { post, put, reset, clearErrors } = form;

    const handleReset = useCallback(() => {
        reset();
        clearErrors();
    }, [reset, clearErrors]);

    const handleSubmit = useCallback((e: SubmitEvent<HTMLFormElement>) => {
            e.preventDefault();
            if (isUpdate && subject) {
                put(update({ id: subject?.id }).url, {
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
        [isUpdate, subject, put, handleReset, post],
    );



    return {
        form,
        isUpdate,
        handleSubmit,
        handleReset,
    };
};
