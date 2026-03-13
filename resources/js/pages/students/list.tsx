import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    FileUp,
    Filter,
    PlusCircle,
    Search,
    X,
} from 'lucide-react';

import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';
import Layout from '@/components/layouts/admin/layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTable } from '@/components/utils/data-table';
import { useDebounce } from '@/hooks';
import { columnStudentList } from '@/modules/users/components';
import type { StudentListPaginator, StudentSearch } from '@/modules/users/types';
import {
    getGradeLevelLabel,
} from '@/modules/users/utils';
import { PageHeader } from '@/components/utils';


interface Props {
    students: StudentListPaginator;
    filters: StudentSearch;
}

export default function Page({ filters, students }: Props) {

    console.log(filters);
    const [searchTerm, setSearchTerm] = useState(filters?.keyword || '');
    const fetchData = useCallback((search: string, grade?: GradeLevel) => {
        router.get(
            listStudent(),
            { search, grade },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, []);

    const debouncedFetch = useDebounce((searchValue: string) => {
        fetchData(searchValue, filters?.grade);
    }, 500);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchTerm(value);
        debouncedFetch(value);
    };

    const clearSearch = () => {
        setSearchTerm('');
        fetchData('', filters?.grade);
    };

    const handleGradeChange = (value: string) => {
        const gradeValue = value === 'all' ? '' : value;
        fetchData(searchTerm, gradeValue);
    };

    const gradeOptions = Array.from({ length: 13 }, (_, i) => ({
        value: i.toString(),
        label: getGradeLevelLabel(i),
    }));

    return (
        <div className="flex min-h-screen w-full flex-col gap-6 bg-slate-50/50 p-6 lg:p-8">
            <Head title="Quản lý Học sinh" />

            {/* 1. HEADER & ACTIONS */}
            <PageHeader
                title="Danh sách Học sinh"
                description="Quản lý hồ sơ, theo dõi quá trình học tập và thông tin liên lạc."
            >
                {/* Truyền các Button vào đây như con của PageHeader */}
                <Button
                    variant="outline"
                    className="bg-white text-slate-700 shadow-sm hover:bg-slate-100"
                >
                    <FileUp className="mr-2 h-4 w-4" />
                    <span className="hidden lg:inline">Nhập Excel</span>
                    <span className="lg:hidden">Nhập</span>
                </Button>

                <Button
                    variant="outline"
                    className="bg-white text-slate-700 shadow-sm hover:bg-slate-100"
                >
                    <Download className="mr-2 h-4 w-4" />
                    <span className="hidden lg:inline">Xuất dữ liệu</span>
                    <span className="lg:hidden">Xuất</span>
                </Button>

                <Button
                    className="bg-amber-500 text-white shadow-sm transition-all hover:bg-amber-600"
                >
                    <PlusCircle className="mr-2 h-4 w-4" />
                    Thêm học sinh
                </Button>
            </PageHeader>

            {/* BỘ LỌC (FILTER CARD) - Đã được tách riêng */}
            <Card className="overflow-hidden border-none shadow-sm ring-1 ring-slate-200">
                {/* Header của Card Bộ lọc */}
                <CardHeader className="border-b border-slate-100 bg-slate-50/50 px-6 pt-5 pb-4">
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-800">
                            <div className="flex size-7 items-center justify-center rounded-md bg-white">
                                <Filter className="h-3.5 w-3.5 text-slate-600" />
                            </div>
                            Bộ lọc & Tìm kiếm
                        </CardTitle>
                        {/* Nút xóa bộ lọc đưa lên góc phải trên cùng */}
                        {(searchTerm) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearSearch}
                                className="h-8 text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                            >
                                <X className="mr-1.5 h-3.5 w-3.5" />
                                Xóa bộ lọc
                            </Button>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="p-6">
                    {/* Bố cục lưới: 1 cột trên mobile, 2 cột trên tablet, 4 cột trên Desktop */}
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        {/* 1. Từ khóa tìm kiếm */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                Từ khóa
                            </label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                <Input
                                    type="text"
                                    placeholder="Tên, SĐT phụ huynh..."
                                    className="border-slate-200 bg-slate-50 pl-9 focus-visible:bg-white focus-visible:ring-amber-500"
                                    value={searchTerm}
                                    onChange={handleSearchChange}
                                />
                            </div>
                        </div>

                        {/* 2. Khối / Lớp */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                Khối học
                            </label>
                            <Select
                                value={filters?.grade?.toString() || 'all'}
                                onValueChange={handleGradeChange}
                            >
                                <SelectTrigger className="border-slate-200 bg-slate-50 focus:bg-white focus:ring-amber-500">
                                    <SelectValue placeholder="Tất cả khối" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Tất cả khối
                                    </SelectItem>
                                    {gradeOptions.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* 3. Giới tính (Tương lai bạn nối API sau) */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                Giới tính
                            </label>
                            <Select defaultValue="all">
                                <SelectTrigger>
                                    <SelectValue placeholder="Tất cả" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tất cả</SelectItem>
                                    <SelectItem value="male">Nam</SelectItem>
                                    <SelectItem value="female">Nữ</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* 4. Trạng thái (Tương lai bạn nối API sau) */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                Trạng thái
                            </label>
                            <Select defaultValue="active">
                                <SelectTrigger className="border-slate-200 bg-slate-50 focus:bg-white focus:ring-amber-500">
                                    <SelectValue placeholder="Trạng thái" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tất cả</SelectItem>
                                    <SelectItem value="active">
                                        Đang theo học
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Đã nghỉ học
                                    </SelectItem>
                                    <SelectItem value="reserved">
                                        Bảo lưu
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* 4. KHU VỰC BẢNG DỮ LIỆU (DATA TABLE CARD) */}
            <DataTable
                columns={columnStudentList}
                paginator={students}
                emptyState={
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <div className="mb-4 flex size-16 items-center justify-center rounded-full bg-slate-50 ring-8 ring-slate-50/50">
                            <Search className="h-8 w-8 text-slate-400" />
                        </div>
                        <h3 className="text-lg font-semibold text-slate-900">
                            Không tìm thấy học sinh
                        </h3>
                        <p className="mx-auto mt-2 mb-6 text-sm text-slate-500">
                            {'Hệ thống chưa không tìm thấy dữ liệu'}
                        </p>
                        {!(searchTerm || filters?.grade) && (
                            <Button
                                asChild
                                className="bg-amber-500 text-white hover:bg-amber-600"
                            >
                                <Link>
                                    <PlusCircle className="mr-2 h-4 w-4" />
                                    Thêm học sinh ngay
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />
        </div>
    );
}
Page.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Danh sách học sinh' }]}>{page}</Layout>
);
