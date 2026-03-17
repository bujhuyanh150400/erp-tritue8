import { RoomStatus } from '@/modules/room/consts';


export const getRoomStatusLabel = (status: RoomStatus) => {
    switch (status) {
        case RoomStatus.Active:
            return 'Hoạt động';
        case RoomStatus.Locked:
            return 'Bảo trì';
        case RoomStatus.Maintenance:
            return 'Hết sức';
        default:
            return 'Trạng thái không xác định';
    }
};

export const getRoomStatusStyleBadge = (status: RoomStatus) => {
    switch (status) {
        case RoomStatus.Active:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-green-50 text-green-700 border-green-200';
        case RoomStatus.Locked:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-red-50 text-red-700 border-red-200';
        case RoomStatus.Maintenance:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-yellow-50 text-yellow-700 border-yellow-200';
        default:
            return 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium bg-gray-50 text-gray-700 border-gray-200';
    }
};
