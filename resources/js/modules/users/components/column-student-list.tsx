import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, History, Lock, MoreHorizontal, Pencil } from 'lucide-react';
import {
    viewUpdate,
} from '@/actions/App/Http/Controllers/StudentController';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn, formatDate } from '@/lib/utils';
import type { StudentList } from '@/modules/users/types';
import { getGenderClassStyleBadge, getGenderLabel, getGradeLevelLabel } from '@/modules/users/utils';

export const columnStudentList: ColumnDef<StudentList>[] = [
    {
        accessorKey: 'full_name',
        header: 'Học sinh',
        cell: ({ row }) => {

            return (
                <div className="flex items-center gap-3">
                    <div className="flex flex-col">
                        <span className="font-semibold text-foreground">
                            {row.original.full_name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Id: {row.original.user.id}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Dob: {formatDate(row.original.dob)}
                        </span>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'gender',
        header: 'Giới tính',
        cell: ({ row }) => {
            const gender = row.original.gender;
            const label = getGenderLabel(gender);
            const badgeClass = getGenderClassStyleBadge(gender);
            return <span className={badgeClass}>{label}</span>;
        },
    },
    {
        accessorKey: 'grade_level',
        header: 'Khối/Lớp',
        cell: ({ row }) => (
            <span className="inline-flex items-center rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground ring-1 ring-secondary-foreground/10 ring-inset">
                {getGradeLevelLabel(row.original.grade_level)}
            </span>
        ),
    },
    {
        id: 'parent_info',
        header: 'Phụ huynh',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium">{row.original.parent_name}</span>
                <span className="text-xs text-muted-foreground">
                    📞 {row.original.parent_phone}
                </span>
            </div>
        ),
    },
    {
        accessorKey: 'note',
        header: 'Ghi chú',
        cell: ({ row }) => (
            <span
                className="inline-block max-w-37.5 truncate text-sm text-muted-foreground"
                title={row.original.note || ''}
            >
                {row.original.note || '-'}
            </span>
        ),
    },
    {
        accessorKey: 'is_active',
        header: 'Trạng thái',
        cell: ({ row }) => (
            <span
                className={cn(
                    'inline-block max-w-37.5 truncate text-sm',
                    row.original.user.is_active
                        ? 'text-green-600'
                        : 'text-red-600',
                )}
                title={
                    row.original.user.is_active
                        ? 'Học sinh đang hoạt động'
                        : 'Học sinh đã bị khóa'
                }
            >
                {row.original.user.is_active ? 'Hoạt động' : 'Khóa'}
            </span>
        ),
    },

    {
        id: 'actions',
        cell: ({ row }) => {
            const student = row.original;
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="h-8 w-8 p-0 hover:bg-muted"
                        >
                            <span className="sr-only">Mở menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        {/* Nhóm 1: Xem & Sửa */}
                        <DropdownMenuItem className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4 text-slate-500" />
                            <span>Xem chi tiết</span>
                        </DropdownMenuItem>

                        <DropdownMenuItem className="cursor-pointer">
                            <Link href={viewUpdate({id: student.user.id})} className={"w-full flex items-center gap-2"}>
                                <Pencil className="mr-2 h-4 w-4 text-blue-600" />
                                <span>Chỉnh sửa thông tin</span>
                            </Link>
                        </DropdownMenuItem>

                        <DropdownMenuSeparator />

                        {/* Nhóm 2: Lịch sử */}
                        <DropdownMenuItem className="cursor-pointer">
                            <History className="mr-2 h-4 w-4 text-amber-500" />
                            <span>Lịch sử đổi thưởng</span>
                        </DropdownMenuItem>

                        <DropdownMenuSeparator />

                        {/* Nhóm 3: Quản lý trạng thái (Tài khoản) */}
                        <DropdownMenuItem
                            className={cn('cursor-pointer', {
                                'text-red-600 focus:bg-orange-50 focus:text-orange-600':
                                    student.user.is_active,
                                'text-emerald-600 focus:bg-emerald-50 focus:text-emerald-600':
                                    !student.user.is_active,
                            })}
                        >
                            <Lock className="mr-2 h-4 w-4" />
                            <span>
                                {student.user.is_active
                                    ? 'Khóa tài khoản'
                                    : 'Mở khóa tài khoản'}
                            </span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
