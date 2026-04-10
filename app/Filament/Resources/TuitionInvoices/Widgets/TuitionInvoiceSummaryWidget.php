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

    protected function getTablePage(): string
    {
        return ListTuitionInvoices::class;
    }

    protected function getStats(): array
    {
        $filters = $this->tableFilters['filters'] ?? [];
        $summary = app(TuitionInvoiceRepository::class)->getSummaryStats($filters);

        return [
            Stat::make('Tổng học phí tháng', number_format((int) $summary->total_study_fee, 0, ',', '.') . 'đ')
                ->description('Tổng học phí phát sinh trong tháng đang lọc')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
            Stat::make('Tổng nợ cũ', number_format((int) $summary->total_previous_debt, 0, ',', '.') . 'đ')
                ->description('Khoản công nợ chuyển từ kỳ trước')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Tổng phải thu', number_format((int) $summary->total_remaining, 0, ',', '.') . 'đ')
                ->description('Số tiền còn phải thu sau khi trừ đã thanh toán')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
