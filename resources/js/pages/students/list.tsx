import { Head, Link } from '@inertiajs/react';
import { Download, FileUp, PlusCircle, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { viewCreate } from '@/actions/App/Http/Controllers/StudentController';
import Layout from '@/components/layouts/admin/layout';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    FilterCard,
    FormFieldInput,
    PageHeader,
    FormFieldSelect,
} from '@/components/utils';
import { DataTable } from '@/components/utils/data-table';
import { isFilterActive, mapEnumToOptions } from '@/lib/utils';
import { columnStudentList } from '@/modules/users/components';
import { GradeLevel } from '@/modules/users/consts';
import { useStudentList } from '@/modules/users/hooks';
import type {
    StudentListPaginator,
    StudentSearchRequest,
} from '@/modules/users/types';
import {  getGradeLevelLabel } from '@/modules/users/utils';



const gradeOptions = mapEnumToOptions(
    GradeLevel,
    getGradeLevelLabel,
    'Tất cả',
);


interface Props {
    students: StudentListPaginator;
    filters: StudentSearchRequest;
}

export default function Page({ filters, students }: Props) {
    const {
        filters: searchFilters,
        setFilters,
        clearFilters,
        triggerSearch,
        isLoading,
    } = useStudentList(filters);
    return (
        <div className="flex min-h-screen w-full flex-col gap-6 bg-slate-50/50 p-6 lg:p-8">
            <Head title="Quản lý Học sinh" />

            {/* 1. HEADER & ACTIONS */}
            <PageHeader
                title="Danh sách Học sinh"
                description="Quản lý hồ sơ, theo dõi quá trình học tập và thông tin liên lạc."
            >
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

                <Button variant="default" className="" asChild>
                    <Link href={viewCreate()}>
                        <PlusCircle className="mr-2 h-4 w-4" />
                        Thêm học sinh
                    </Link>
                </Button>
            </PageHeader>

            {/* 2. BỘ LỌC (FILTER CARD) */}
            <FilterCard
                onClear={clearFilters}
                onSubmit={() => triggerSearch(searchFilters)}
                isLoading={isLoading}
                showClear={isFilterActive(searchFilters)}
            >
                <FormFieldInput
                    id="search"
                    label="Từ khóa"
                    leftIcon={<Search className="h-4 w-4 text-slate-400" />}
                    placeholder="Tên, SĐT, Mã học sinh..."
                    value={searchFilters.keyword || ''}
                    onChange={(e) =>
                        setFilters((draft) => {
                            draft.keyword = e.target.value;
                        })
                    }
                />

                <FormFieldSelect
                    id="grade_level"
                    label="Khối học"
                    options={gradeOptions}
                    value={searchFilters.grade_level?.toString() || 'all'} // Lấy trực tiếp từ server để làm chuẩn
                    onValueChange={(value) =>
                        setFilters((draft) => {
                            draft.grade_level =
                                value === 'all'
                                    ? undefined
                                    : (Number(value) as GradeLevel);
                        })
                    }
                />
            </FilterCard>

            {/* 3. BẢNG DỮ LIỆU */}
            <div className="flex flex-col overflow-hidden rounded-xl border-none bg-white shadow-sm ring-1 ring-slate-200">
                <DataTable
                    columns={columnStudentList}
                    paginator={students}
                    emptyState={
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Search className="h-8 w-8 text-slate-400" />
                                </EmptyMedia>
                                <EmptyTitle>Không tìm thấy học sinh</EmptyTitle>
                                <EmptyDescription>
                                    Không có kết quả nào khớp với bộ lọc hiện
                                    tại.
                                </EmptyDescription>
                            </EmptyHeader>
                            <EmptyContent className="flex-row justify-center gap-2">
                                <Button asChild>
                                    <Link href={viewCreate()}>
                                        <PlusCircle className="mr-2 h-4 w-4" />
                                        Thêm học sinh ngay
                                    </Link>
                                </Button>
                            </EmptyContent>
                        </Empty>
                    }
                />
            </div>
        </div>
    );
}

Page.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Danh sách học sinh' }]}>{page}</Layout>
);
