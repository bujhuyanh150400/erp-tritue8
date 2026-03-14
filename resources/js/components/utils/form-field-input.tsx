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
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
    InputGroupText,
    InputGroupTextarea,
} from '@/components/ui/input-group';
import { cn } from '@/lib/utils';

export interface FormFieldInputProps extends React.InputHTMLAttributes<
    HTMLInputElement | HTMLTextAreaElement
> {
    label?: string;
    description?: string;
    error?: string;
    leftIcon?: React.ReactNode;
    rightElement?: React.ReactNode;
}

export const FormFieldInput = React.forwardRef<
    HTMLInputElement & HTMLTextAreaElement,
    FormFieldInputProps
>(
    (
        {
            id,
            label,
            description,
            error,
            leftIcon,
            rightElement,
            type = 'text',
            required,
            className,
            ...props
        },
        ref,
    ) => {
        const [showPassword, setShowPassword] = React.useState(false);

        const isPassword = type === 'password';
        const isTextarea = type === 'textarea';

        const inputType = isPassword
            ? showPassword
                ? 'text'
                : 'password'
            : type;

        return (
            <Field className="w-full">
                {label && (
                    <FieldLabel htmlFor={id}>
                        {label}
                        {required && (
                            <span className="ml-1 text-destructive">*</span>
                        )}
                    </FieldLabel>
                )}

                {/* BỌC TẤT CẢ VÀO TRONG INPUT GROUP */}
                <InputGroup
                    className={
                        error ? 'border-destructive ring-destructive' : ''
                    }
                >
                    {/* LEFT ICON HOẶC TEXT */}
                    {leftIcon && (
                        <InputGroupAddon align="inline-start">
                            {/* Nếu truyền vào là string (vd: "https://"), bọc bằng InputGroupText để format chuẩn */}
                            {typeof leftIcon === 'string' ? (
                                <InputGroupText>{leftIcon}</InputGroupText>
                            ) : (
                                leftIcon
                            )}
                        </InputGroupAddon>
                    )}

                    {/* RENDER DỰA THEO TYPE */}
                    {isTextarea ? (
                        <InputGroupTextarea
                            ref={ref as React.Ref<HTMLTextAreaElement>}
                            id={id}
                            required={required}
                            className={cn('min-h-20 resize-y', className)}
                            aria-invalid={!!error}
                            {...(props as React.TextareaHTMLAttributes<HTMLTextAreaElement>)}
                        />
                    ) : (
                        <InputGroupInput
                            ref={ref as React.Ref<HTMLInputElement>}
                            id={id}
                            type={inputType}
                            required={required}
                            className={className}
                            aria-invalid={!!error}
                            {...(props as React.InputHTMLAttributes<HTMLInputElement>)}
                        />
                    )}

                    {/* RIGHT ELEMENT TÙY CHỈNH */}
                    {rightElement && (
                        <InputGroupAddon align="inline-end">
                            {typeof rightElement === 'string' ? (
                                <InputGroupText>{rightElement}</InputGroupText>
                            ) : (
                                rightElement
                            )}
                        </InputGroupAddon>
                    )}

                    {/* NÚT TOGGLE PASSWORD */}
                    {isPassword && (
                        <InputGroupAddon align="inline-end">
                            <InputGroupButton
                                type="button"
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
                                {showPassword ? (
                                    <EyeOff className="h-4 w-4" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                            </InputGroupButton>
                        </InputGroupAddon>
                    )}
                </InputGroup>

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
