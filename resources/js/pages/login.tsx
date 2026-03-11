'use client';

import Banner from '@/assets/images/banner_1.png';
import Logo from '@/assets/images/logo.png';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldSeparator } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { FormFieldInput } from '@/components/utils';
import { cn } from '@/lib/utils';
import { useLogin } from '@/modules/auth/hooks';

export default function LoginPage() {
    const { form, submitForm } = useLogin();

    const { data, setData, processing, errors } = form;

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="flex flex-col gap-4 p-6 md:p-10">
                <div className="flex justify-center gap-2 md:justify-start">
                    <a href="/" className="flex items-center gap-2 font-medium">
                        <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <Avatar>
                                <AvatarImage src={Logo} alt="Tritue8+" />
                            </Avatar>
                        </div>
                        Tritue8+
                    </a>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-xs">
                        <form
                            className={cn('flex flex-col gap-6')}
                            onSubmit={submitForm}
                        >
                            <FieldGroup>
                                <div className="flex flex-col items-center gap-1 text-center">
                                    <h1 className="text-2xl font-bold">
                                        Đăng nhập hệ thống
                                    </h1>
                                    <p className="text-sm text-balance text-muted-foreground">
                                        Bạn hãy nhập tài khoản và mật khẩu để
                                        đăng nhập hệ thống.
                                    </p>
                                </div>
                                <FormFieldInput
                                    value={data.username}
                                    onChange={(e) =>
                                        setData('username', e.target.value)
                                    }
                                    id={'username'}
                                    label={'Tài khoản'}
                                    type={'text'}
                                    placeholder={'Nhập tài khoản'}
                                    required
                                    error={errors.username}
                                />

                                <FormFieldInput
                                    label={'Mật khẩu'}
                                    type={'password'}
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    error={errors.password}
                                    placeholder="Nhập mật khẩu"
                                    required
                                    description={
                                        'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt'
                                    }
                                />

                                <Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        Đăng nhập
                                    </Button>
                                </Field>
                                <FieldSeparator>
                                    Bạn chưa có tài khoản? Liên hệ quản trị viên
                                    để được cấp quyền truy cập.
                                </FieldSeparator>
                            </FieldGroup>
                        </form>
                    </div>
                </div>
            </div>
            <div className="relative hidden bg-muted lg:block">
                <img
                    src={Banner}
                    alt="Image"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
            </div>
        </div>
    );
}
