import type {
    ColumnDef,
    RowSelectionState,
    OnChangeFn,
} from '@tanstack/react-table';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DataTablePagination } from '@/components/utils/pagination';
import type { LaravelPaginator } from '@/lib/types';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    paginator: LaravelPaginator<TData>;
    onRowClick?: (row: TData) => void;
    rowSelection?: RowSelectionState;
    onRowSelectionChange?: OnChangeFn<RowSelectionState>;
    emptyState?: ReactNode;
}

export function DataTable<TData, TValue>({
    columns,
    paginator,
    onRowClick,
    rowSelection,
    onRowSelectionChange,
    emptyState,
}: DataTableProps<TData, TValue>) {
    const [internalRowSelection, setInternalRowSelection] =
        useState<RowSelectionState>({});

    const selectionState =
        rowSelection !== undefined ? rowSelection : internalRowSelection;

    const handleRowSelectionChange: OnChangeFn<RowSelectionState> = (
        updaterOrValue,
    ) => {
        if (onRowSelectionChange) {
            onRowSelectionChange(updaterOrValue);
            return;
        }

        setInternalRowSelection((prev) =>
            typeof updaterOrValue === 'function'
                ? (
                      updaterOrValue as (
                          old: RowSelectionState,
                      ) => RowSelectionState
                  )(prev)
                : updaterOrValue,
        );
    };

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: paginator.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        enableRowSelection: true,
        onRowSelectionChange: handleRowSelectionChange,
        state: {
            rowSelection: selectionState,
        },
    });

    return (
        <div>
            <div className="mb-4 overflow-hidden rounded-md border bg-white">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={
                                        row.getIsSelected() && 'selected'
                                    }
                                    className={
                                        onRowClick
                                            ? 'cursor-pointer hover:bg-muted/50'
                                            : ''
                                    }
                                    onClick={() => onRowClick?.(row.original)}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    {/* Sử dụng props emptyState nếu có, nếu không thì dùng mặc định */}
                                    {emptyState ? (
                                        emptyState
                                    ) : (
                                        <div className="flex flex-col items-center justify-center py-10">
                                            <p className="text-muted-foreground">
                                                Không có dữ liệu nào để hiển thị.
                                            </p>
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <DataTablePagination paginator={paginator} />
        </div>
    );
}
