<?php

namespace App\Filament\Resources\TuitionInvoices\Widgets;

use App\Filament\Resources\TuitionInvoices\Pages\ListTuitionInvoices;
use App\Repositories\TuitionInvoiceRepository;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TuitionInvoiceSummaryWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getColumns(): int
    {
        return 6;
    }

    protected function getTablePage(): string
    {
        return ListTuitionInvoices::class;
    }

    protected function getStats(): array
    {
        $filters = $this->tableFilters['filters'] ?? [];
        $summary = app(TuitionInvoiceRepository::class)->getSummaryStats($filters);

        return [
            Stat::make('Tổng số học sinh đã đóng học phí', number_format((int) $summary->paid_student_count, 0, ',', '.'))
                ->description('Đã phát sinh ít nhất một lần thanh toán')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Tổng số học sinh chưa đóng học phí', number_format((int) $summary->unpaid_student_count, 0, ',', '.'))
                ->description('Còn số tiền chưa tất toán')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
            Stat::make('Số tiền đã tất toán', number_format((int) $summary->total_paid_amount, 0, ',', '.') . 'đ')
                ->description('Tổng đã thu')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Số tiền chưa tất toán', number_format((int) $summary->total_unpaid_amount, 0, ',', '.') . 'đ')
                ->description('Tổng còn phải thu')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger'),
            Stat::make('Thanh toán Tiền mặt', number_format((int) $summary->total_cash_amount, 0, ',', '.') . 'đ')
                ->description('Thanh toán tiền mặt')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('warning'),
            Stat::make('Thanh toán Chuyển khoản', number_format((int) $summary->total_bank_transfer_amount, 0, ',', '.') . 'đ')
                ->description('Thanh toán chuyển khoản')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),
        ];
    }
}
