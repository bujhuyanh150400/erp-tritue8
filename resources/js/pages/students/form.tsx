'use client';

import {
    Save,
    RotateCcw,
    UserPlus,
    Info,
    Phone,
    ShieldCheck,
    RefreshCw,
} from 'lucide-react';
import * as React from 'react';

import type { ReactNode } from 'react';
import Layout from '@/components/layouts/admin/layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FormFieldInput, FormFieldSelect } from '@/components/utils';

import { mapEnumToOptions } from '@/lib/utils';
import { Gender, GradeLevel } from '@/modules/users/consts';
import { useStudentForm } from '@/modules/users/hooks';
import type { StudentItem } from '@/modules/users/types';
import { getGenderLabel, getGradeLevelLabel } from '@/modules/users/utils';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';

const gradeOptions = mapEnumToOptions(
    GradeLevel,
    getGradeLevelLabel,
);

const genderOptions = mapEnumToOptions(Gender, getGenderLabel);

type Props = {
        student?: StudentItem;
}

export default function Page({ student }: Props) {
    const {
        form,
        isUpdate,
        handleFullNameChange,
        handleRandomPassword,
        handleSubmit,
        handleReset,
    } = useStudentForm(student);

    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={handleSubmit}>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <UserPlus className="h-5 w-5 text-amber-500" />
                        {isUpdate
                            ? 'Chỉnh sửa hồ sơ học sinh'
                            : 'Tạo hồ sơ học sinh'}
                    </CardTitle>
                </CardHeader>

                <CardContent className="space-y-8 px-6 py-4">
                    {/* KHU VỰC TÀI KHOẢN */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 border-l-4 border-emerald-500 pl-3">
                            <ShieldCheck className="h-4 w-4 text-slate-400" />
                            <h3 className="text-sm font-bold tracking-wider text-slate-600 uppercase">
                                Thông tin tài khoản
                            </h3>
                        </div>
                        <div className="grid gap-6 md:grid-cols-2">
                            <FormFieldInput
                                required={!isUpdate}
                                label="Tên đăng nhập (Tự động)"
                                description={
                                    isUpdate
                                        ? 'Tên đăng nhập không thể thay đổi sau khi tạo.'
                                        : 'Tên đăng nhập sẽ tự động tạo dựa trên tên đầy đủ hoặc nhập thủ công.'
                                }
                                disabled={isUpdate}
                                readOnly={isUpdate}
                                placeholder="VD:hs_nguyenvana"
                                value={data.user_name}
                                onChange={(e) =>
                                    setData('user_name', e.target.value)
                                }
                                error={errors.user_name}
                            />
                            <FormFieldInput
                                required={!isUpdate}
                                label={
                                    isUpdate ? 'Đổi mật khẩu' : 'Mật khẩu mới'
                                }
                                type="password"
                                description={
                                    isUpdate
                                        ? 'Nhập mật khẩu mới nếu muốn đổi. Để trống nếu muốn giữ nguyên mật khẩu cũ.'
                                        : 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt'
                                }
                                placeholder="********"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                error={errors.password}
                                rightElement={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleRandomPassword}
                                        className="h-7 px-2 text-xs text-slate-500 hover:text-slate-900"
                                    >
                                        <RefreshCw className="mr-1 h-3 w-3" />
                                        Random
                                    </Button>
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-8 md:grid-cols-2">
                        {/* KHU VỰC THÔNG TIN CÁ NHÂN */}
                        <div className="space-y-5">
                            <div className="flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                                <Info className="h-4 w-4 text-slate-400" />
                                <h3 className="text-sm font-bold tracking-wider text-slate-600 uppercase">
                                    Cá nhân
                                </h3>
                            </div>
                            <FormFieldInput
                                label="Họ và tên đầy đủ"
                                required
                                value={data.full_name}
                                onChange={handleFullNameChange}
                                error={errors.full_name}
                            />
                            <div className="grid grid-cols-2 gap-4">
                                <FormFieldInput
                                    label="Ngày sinh"
                                    type="date"
                                    required
                                    value={data.dob}
                                    onChange={(e) =>
                                        setData('dob', e.target.value)
                                    }
                                    error={errors.dob}
                                />
                                <FormFieldSelect
                                    label="Giới tính"
                                    required
                                    options={genderOptions}
                                    value={data.gender.toString()}
                                    onValueChange={(val) =>
                                        setData('gender', Number(val))
                                    }
                                    error={errors.gender}
                                />
                            </div>
                            <FormFieldSelect
                                label="Khối học"
                                required
                                options={gradeOptions.filter(
                                    (opt) => opt.value !== 'all',
                                )}
                                value={data.grade_level.toString()}
                                onValueChange={(val) =>
                                    setData('grade_level', Number(val))
                                }
                                error={errors.grade_level}
                            />
                        </div>

                        {/* KHU VỰC LIÊN HỆ */}
                        <div className="space-y-5">
                            <div className="flex items-center gap-2 border-l-4 border-blue-500 pl-3">
                                <Phone className="h-4 w-4 text-slate-400" />
                                <h3 className="text-sm font-bold tracking-wider text-slate-600 uppercase">
                                    Gia đình
                                </h3>
                            </div>
                            <FormFieldInput
                                label="Tên bố/mẹ"
                                required
                                value={data.parent_name}
                                onChange={(e) =>
                                    setData('parent_name', e.target.value)
                                }
                                error={errors.parent_name}
                            />
                            <FormFieldInput
                                label="Số điện thoại"
                                required
                                value={data.parent_phone}
                                onChange={(e) =>
                                    setData('parent_phone', e.target.value)
                                }
                                error={errors.parent_phone}
                            />
                            <FormFieldInput
                                label="Địa chỉ"
                                required
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                                error={errors.address}
                            />
                        </div>
                    </div>

                    <FormFieldInput
                        label="Ghi chú"
                        description={
                            'Ghi chú về học sinh, ví dụ: có vấn đề sức khỏe, có vấn đề học tập, ...'
                        }
                        type="textarea"
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                        error={errors.note}
                    />
                </CardContent>

                <CardFooter className="flex justify-end gap-3 border-t p-4">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={handleReset}
                        disabled={processing}
                    >
                        <RotateCcw className="mr-2 h-4 w-4" /> Làm lại
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-amber-500 px-8 text-white shadow-sm hover:bg-amber-600"
                    >
                        {processing ? (
                            <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        ) : (
                            <Save className="mr-2 h-4 w-4" />
                        )}
                        {isUpdate ? 'Cập nhật' : 'Tạo hồ sơ học sinh'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

Page.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Danh sách học sinh', href: listStudent().url }, { title: 'Form học sinh',  }]}>{page}</Layout>
);

