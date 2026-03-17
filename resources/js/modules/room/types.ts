import type { BaseSearchRequest, LaravelPaginator } from '@/lib/types';
import type { RoomStatus } from '@/modules/room/consts';

export type RoomListItem = {
    id: number;
    name: string;
    capacity: number;
    active_classes_count: number;
    status: RoomStatus;
    note: string | null;
}

export type RoomListPaginator = LaravelPaginator<RoomListItem>;


export type RoomSearchRequest = BaseSearchRequest<{
    keyword?: string;
    status?: RoomStatus;
}>;
