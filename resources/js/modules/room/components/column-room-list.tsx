import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    CalendarDays,
    MoreHorizontal,
    Pencil,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { RoomListItem } from '@/modules/room/types';
import {
    getRoomStatusLabel,
    getRoomStatusStyleBadge,
} from '@/modules/room/utils';

export const columnRoomList: ColumnDef<RoomListItem>[] = [
    {
        accessorKey: 'name',
        header: 'Tên phòng',
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-3">
                    <div className="flex flex-col">
                        <span className="font-semibold text-foreground">
                            {row.original.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Id: {row.original.id}
                        </span>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'capacity',
        header: 'Sức chứa',
        cell: ({ row }) => <div>{row.original.capacity} học viên</div>,
    },
    {
        accessorKey: 'active_classes_count',
        header: 'Lớp đang HĐ',
        cell: ({ row }) => (
            <div className="text-center font-mono">
                {row.original.active_classes_count || 0}
            </div>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Trạng thái',
        cell: ({ row }) => {
            const styleClass = getRoomStatusStyleBadge(row.original.status);
            return (
                <div className={styleClass}>
                    {getRoomStatusLabel(row.original.status)}
                </div>
            );
        },
    },
    {
        accessorKey: 'note',
        header: 'Ghi chú',
        cell: ({ row }) => (
            <div className="max-w-[200px] truncate text-slate-500">
                {row.original.note || '---'}
            </div>
        ),
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {/* Xem lịch phòng */}
                        <DropdownMenuItem asChild className="cursor-pointer">
                            <Link>
                                <CalendarDays className="mr-2 h-4 w-4 text-emerald-600" />
                                Xem lịch phòng
                            </Link>
                        </DropdownMenuItem>

                        {/* Chỉnh sửa */}
                        <DropdownMenuItem asChild className="cursor-pointer">
                            <Link>
                                <Pencil className="mr-2 h-4 w-4 text-blue-600" />
                                Chỉnh sửa
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
