'use client';

import * as React from 'react';
import {
    Field,
    FieldLabel,
    FieldDescription,
    FieldError,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface SelectOption {
    value: string;
    label: string;
}

export interface FormFieldSelectProps {
    id?: string;
    label?: string;
    description?: string;
    error?: string;
    placeholder?: string;
    required?: boolean;
    options: SelectOption[];
    value?: string;
    onValueChange?: (value: string) => void;
    defaultValue?: string;
    disabled?: boolean;
    className?: string;
}

export const FormFieldSelect = React.forwardRef<
    HTMLButtonElement,
    FormFieldSelectProps
>(
    (
        {
            id,
            label,
            description,
            error,
            placeholder = 'Chọn một tùy chọn',
            required,
            options,
            value,
            onValueChange,
            defaultValue,
            disabled,
            className,
        },
        ref,
    ) => {
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

                <Select
                    value={value}
                    onValueChange={onValueChange}
                    defaultValue={defaultValue}
                    disabled={disabled}
                >
                    <SelectTrigger
                        ref={ref}
                        id={id}
                        className={className}
                        aria-invalid={!!error}
                    >
                        <SelectValue placeholder={placeholder} />
                    </SelectTrigger>
                    <SelectContent position={"popper"}>
                        {options.length > 0 ? (
                            options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))
                        ) : (
                            <div className="relative py-2 pr-2 pl-8 text-sm text-muted-foreground">
                                Không có dữ liệu
                            </div>
                        )}
                    </SelectContent>
                </Select>

                {error ? (
                    <FieldError>{error}</FieldError>
                ) : description ? (
                    <FieldDescription>{description}</FieldDescription>
                ) : null}
            </Field>
        );
    },
);

FormFieldSelect.displayName = 'FormFieldSelect';
