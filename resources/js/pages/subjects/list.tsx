import { Head, Link } from '@inertiajs/react';
import {  PlusCircle, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { viewCreate } from '@/actions/App/Http/Controllers/SubjectController';
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
import type { ActiveStatus} from '@/lib/consts';
import { activeStatusOptions } from '@/lib/consts';
import { isFilterActive } from '@/lib/utils';
import { columnSubjectList } from '@/modules/subject/components';
import { useListSubject } from '@/modules/subject/hooks';
import type {
    SubjectListPaginator,
    SubjectSearchRequest,
} from '@/modules/subject/types';

interface Props {
    subjects: SubjectListPaginator; // Thay bằng Paginator type của bạn
    filters: SubjectSearchRequest;
}

export default function Page({ filters, subjects }: Props) {
    const {
        filters: searchFilters,
        setFilters,
        clearFilters,
        triggerSearch,
        isLoading,
    } = useListSubject(filters);

    return (
        <div className="flex min-h-screen w-full flex-col gap-6 bg-slate-50/50 p-6 lg:p-8">
            <Head title="Quản lý Môn học" />

            <PageHeader
                title="Môn học"
                description={`Quản lý môn học trong hệ thống.`}
            >
                <Button variant="default" asChild>
                    <Link href={viewCreate().url}>
                        <PlusCircle className="mr-2 h-4 w-4" />
                        Thêm môn học
                    </Link>
                </Button>
            </PageHeader>

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
                    placeholder="Tên môn học, mã môn học, ..."
                    value={searchFilters.keyword || ''}
                    onChange={(e) =>
                        setFilters((draft) => {
                            draft.keyword = e.target.value;
                        })
                    }
                />

                <FormFieldSelect
                    label="Trạng thái"
                    options={activeStatusOptions}
                    value={searchFilters.status?.toString() || 'all'}
                    onValueChange={(value) =>
                        setFilters((draft) => {
                            draft.status =
                                value === 'all'
                                    ? undefined
                                    : Number(value) as ActiveStatus;
                        })
                    }
                />
            </FilterCard>

            <div className="flex flex-col overflow-hidden rounded-xl border-none bg-white shadow-sm ring-1 ring-slate-200">
                <DataTable
                    columns={columnSubjectList}
                    paginator={subjects}
                    emptyState={
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Search className="h-8 w-8 text-slate-400" />
                                </EmptyMedia>
                                <EmptyTitle>Không tìm thấy môn học</EmptyTitle>
                                <EmptyDescription>
                                    Không có kết quả nào khớp với bộ lọc hiện
                                    tại.
                                </EmptyDescription>
                            </EmptyHeader>
                            <EmptyContent className="flex-row justify-center gap-2">
                                <Button asChild>
                                    <Link href={viewCreate()}>
                                        <PlusCircle className="mr-2 h-4 w-4" />
                                        Thêm môn học ngay
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
    <Layout breadcrumbs={[{ title: 'Quản lý môn học' }]}>
        {page}
    </Layout>
);
