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
                ->color('warning'),
            Stat::make('Tổng nợ cũ', number_format((int) $summary->total_previous_debt, 0, ',', '.') . 'đ')
                ->color('danger'),
            Stat::make('Tổng phải thu', number_format((int) $summary->total_remaining, 0, ',', '.') . 'đ')
                ->color('success'),
        ];
    }
}
