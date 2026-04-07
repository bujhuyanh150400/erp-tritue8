<?php

namespace App\Filament\Resources\TuitionInvoices\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\TuitionInvoices\TuitionInvoiceResource;
use App\Filament\Resources\TuitionInvoices\Widgets\TuitionInvoiceSummaryWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListTuitionInvoices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TuitionInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::backAction(TuitionInvoiceResource::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TuitionInvoiceSummaryWidget::class,
        ];
    }
}
