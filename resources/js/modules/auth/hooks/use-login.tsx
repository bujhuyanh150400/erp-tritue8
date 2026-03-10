import { useForm } from '@inertiajs/react';
import type { SubmitEvent } from 'react';
import { useCallback } from 'react';
import type { LoginRequest } from '@/modules/auth/types';

export const useLogin = () => {
    const form = useForm<LoginRequest>({
        username: '',
        password: '',
    });

    const submitForm = useCallback(
        (e: SubmitEvent<HTMLFormElement>) => {
            e.preventDefault();
            form.post('/login');
        },
        [form],
    );
    return {
        form,
        submitForm,
    };
};
