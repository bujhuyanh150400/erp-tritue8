'use client';

import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';
import {
    Field,
    FieldLabel,
    FieldDescription,
    FieldError,
} from '@/components/ui/field';
import {
    InputGroup,
    InputGroupAddon, InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';

export interface FormFieldInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    description?: string;
    error?: string;
    leftIcon?: React.ReactNode;
}

export const FormFieldInput = React.forwardRef<
    HTMLInputElement,
    FormFieldInputProps
>(
    (
        {
            id,
            label,
            description,
            error,
            leftIcon,
            type = 'text',
            required,
            className,
            ...props
        },
        ref,
    ) => {
        // State cho toggle password
        const [showPassword, setShowPassword] = React.useState(false);
        const isPassword = React.useMemo(
            () => type === 'password',
            [type],
        )

        // Xác định type thực sự của thẻ input
        const inputType = React.useMemo(() => isPassword
            ? showPassword
                ? 'text'
                : 'password'
            : type,[isPassword, showPassword, type])

        return (
            <Field className="w-full">
                {label && (
                    <FieldLabel htmlFor={id}>
                        {label}
                        {required && (
                            <span className="text-destructive">*</span>
                        )}
                    </FieldLabel>
                )}

                <InputGroup>
                    {/* Vị trí render Icon bên trái (Tùy thuộc vào cách InputGroup của bạn định nghĩa, có thể cần wrapper hoặc không) */}
                    {leftIcon && (
                        <InputGroupAddon align={'inline-start'}>
                            {leftIcon}
                        </InputGroupAddon>
                    )}

                    <InputGroupInput
                        ref={ref}
                        id={id}
                        type={inputType}
                        required={required}
                        className={className}
                        aria-invalid={!!error}
                        {...props}
                    />

                    {isPassword && (
                        <InputGroupAddon align="inline-end">
                            <InputGroupButton
                                type="button" // Thêm cái này để tránh submit form
                                aria-label={
                                    showPassword
                                        ? 'Ẩn mật khẩu'
                                        : 'Hiện mật khẩu'
                                }
                                title={
                                    showPassword
                                        ? 'Ẩn mật khẩu'
                                        : 'Hiện mật khẩu'
                                }
                                size="icon-xs"
                                onClick={() => setShowPassword((prev) => !prev)}
                            >
                                {showPassword ? <EyeOff /> : <Eye />}
                            </InputGroupButton>
                        </InputGroupAddon>
                    )}
                </InputGroup>

                {/* Xử lý Description hoặc Error bằng component chuẩn */}
                {error ? (
                    <FieldError>{error}</FieldError>
                ) : description ? (
                    <FieldDescription>{description}</FieldDescription>
                ) : null}
            </Field>
        );
    },
);

FormFieldInput.displayName = 'FormFieldInput';
