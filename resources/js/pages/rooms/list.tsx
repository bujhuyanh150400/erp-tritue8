import { Head, Link } from '@inertiajs/react';
import { PlusCircle, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { viewCreate } from '@/actions/App/Http/Controllers/RoomController';
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
import { columnRoomList } from '@/modules/room/components';
import { RoomStatus } from '@/modules/room/consts';
import { useRoomList } from '@/modules/room/hooks';
import type {
    RoomListPaginator,
    RoomSearchRequest,
} from '@/modules/room/types';
import { getRoomStatusLabel } from '@/modules/room/utils';

interface Props {
    rooms: RoomListPaginator;
    filters: RoomSearchRequest;
}

const statusRoomOptions = mapEnumToOptions(RoomStatus, getRoomStatusLabel,'Tất cả');

export default function Page({ filters, rooms }: Props) {
    const {
        filters: searchFilters,
        setFilters,
        clearFilters,
        triggerSearch,
        isLoading,
    } = useRoomList(filters);

    return (
        <div className="flex min-h-screen w-full flex-col gap-6 bg-slate-50/50 p-6 lg:p-8">
            <Head title="Quản lý Cơ sở vật chất" />

            {/* HEADER */}
            <PageHeader
                title="Danh sách Phòng học"
                description={`Quản lý toàn bộ phòng học trong hệ thống.`}
            >
                <Button variant="default" asChild>
                    <Link href="/admin/rooms/create">
                        <PlusCircle className="mr-2 h-4 w-4" />
                        Thêm phòng học
                    </Link>
                </Button>
            </PageHeader>

            {/* FILTER CARD */}
            <FilterCard
                onClear={clearFilters}
                onSubmit={() => triggerSearch(searchFilters)}
                isLoading={isLoading}
                showClear={isFilterActive(searchFilters)}
            >
                <FormFieldInput
                    label="Tìm kiếm nhanh"
                    leftIcon={<Search className="h-4 w-4 text-slate-400" />}
                    placeholder="Tên phòng, Mã phòng..."
                    value={searchFilters.keyword || ''}
                    onChange={(e) =>
                        setFilters((draft) => {
                            draft.keyword = e.target.value;
                        })
                    }
                />

                <FormFieldSelect
                    label="Trạng thái"
                    options={statusRoomOptions}
                    value={searchFilters.status?.toString() || 'all'}
                    onValueChange={(value) =>
                        setFilters((draft) => {
                            draft.status =
                                value === 'all'
                                    ? undefined
                                    : (Number(value) as RoomStatus);
                        })
                    }
                />
            </FilterCard>

            {/* DATA TABLE */}
            <DataTable
                columns={columnRoomList}
                paginator={rooms}
                emptyState={
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Search className="h-8 w-8 text-slate-400" />
                            </EmptyMedia>
                            <EmptyTitle>Không tìm thấy phòng học</EmptyTitle>
                            <EmptyDescription>
                                Không có kết quả nào khớp với bộ lọc hiện tại.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent className="flex-row justify-center gap-2">
                            <Button asChild>
                                <Link href={viewCreate()}>
                                    <PlusCircle className="mr-2 h-4 w-4" />
                                    Thêm phòng học ngay
                                </Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                }
            />
        </div>
    );
}

Page.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Danh sách Phòng học' }]}>
        {page}
    </Layout>
);
