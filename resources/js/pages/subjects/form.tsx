'use client';

import { Save, RotateCcw, BookOpen, PenTool, Info } from 'lucide-react';
import * as React from 'react';
import type { ReactNode } from 'react';

import { listSubject } from '@/actions/App/Http/Controllers/SubjectController';
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

import { ActiveStatus, activeStatusFormOptions } from '@/lib/consts';
import { useFormSubject } from '@/modules/subject/hooks';
import type { SubjectItem } from '@/modules/subject/types';


type Props = {
    subject?: SubjectItem;
};

export default function SubjectFormPage({ subject }: Props) {
    const { form, isUpdate, handleSubmit, handleReset } =
        useFormSubject(subject);

    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={handleSubmit}>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        {isUpdate ? (
                            <PenTool className="h-5 w-5 text-blue-500" />
                        ) : (
                            <BookOpen className="h-5 w-5 text-amber-500" />
                        )}
                        {isUpdate ? 'Cập nhật môn học' : 'Thêm mới môn học'}
                    </CardTitle>
                </CardHeader>

                <CardContent className="space-y-6 px-6 py-4">
                    <div className="flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                        <Info className="h-4 w-4 text-slate-400" />
                        <h3 className="text-sm font-bold tracking-wider text-slate-600 uppercase">
                            Thông tin môn học
                        </h3>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        {/* 1. Tên môn học */}
                        <div className="md:col-span-2">
                            <FormFieldInput
                                label="Tên môn học"
                                required
                                placeholder="VD: Toán học, Ngữ văn..."
                                description="Tên môn học phải là duy nhất trong hệ thống."
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                error={errors.name}
                            />
                        </div>

                        {/* 2. Trạng thái */}
                        <div className="md:col-span-2">
                            <FormFieldSelect
                                label="Trạng thái"
                                required
                                options={activeStatusFormOptions}
                                // Convert boolean -> string để hiển thị lên Select
                                value={
                                    data.is_active
                                        ? ActiveStatus.Active.toString()
                                        : ActiveStatus.Inactive.toString()
                                }
                                onValueChange={(val) =>
                                    // Convert string -> boolean để lưu vào form state
                                    setData(
                                        'is_active',
                                        val === ActiveStatus.Active.toString(),
                                    )
                                }
                                error={errors.is_active as string}
                            />
                        </div>

                        {/* 3. Mô tả */}
                        <div className="md:col-span-2">
                            <FormFieldInput
                                label="Mô tả"
                                type="textarea"
                                placeholder="Nhập mô tả chi tiết về môn học (nếu có)..."
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                error={errors.description}
                            />
                        </div>
                    </div>
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
                        {isUpdate ? 'Lưu thay đổi' : 'Tạo môn học'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

SubjectFormPage.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Quản lý đào tạo' , href: listSubject().url }, { title: 'Môn học' }]}>
        {page}
    </Layout>
);
