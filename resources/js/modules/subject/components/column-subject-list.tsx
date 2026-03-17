import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal, Pencil, Power, PowerOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { SubjectItem } from '@/modules/subject/types';
import { Badge } from '@/components/ui/badge';

export const columnSubjectList: ColumnDef<SubjectItem>[] = [
    {
        accessorKey: 'name',
        header: 'Tên môn học',
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
        accessorKey: 'description',
        header: 'Mô tả',
        cell: ({ row }) => (
            <div className="max-w-75 truncate text-slate-500">
                {row.original.description || '---'}
            </div>
        ),
    },
    {
        accessorKey: 'classes_count',
        header: 'Số lớp đang dùng',
        cell: ({ row }) => (
            <div className="text-center font-mono">
                {row.original.classes_count || 0}
            </div>
        ),
    },
    {
        accessorKey: 'is_active',
        header: 'Trạng thái',
        cell: ({ row }) => {
            const isActive = row.original.is_active;
            return (
                <Badge variant={isActive ? 'default' : 'secondary'}>
                    {isActive ? 'Đang hoạt động' : 'Tạm dừng'}
                </Badge>
            );
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="h-8 w-8 p-0">
                        <MoreHorizontal className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem className="cursor-pointer">
                        <Pencil className="mr-2 h-4 w-4 text-blue-600" />
                        Chỉnh sửa
                    </DropdownMenuItem>
                    <DropdownMenuItem className="cursor-pointer text-destructive">
                        {row.original.is_active ? (
                            <>
                                <PowerOff className="mr-2 h-4 w-4" /> Tắt hoạt động
                            </>
                        ) : (
                            <>
                                <Power className="mr-2 h-4 w-4 text-emerald-600" />{' '}Bật hoạt động
                            </>
                        )}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
];
