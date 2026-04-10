<?php

namespace App\Filament\Resources\TuitionInvoices\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\TuitionInvoices\TuitionInvoiceResource;
use App\Filament\Resources\TuitionInvoices\Widgets\TuitionInvoiceSummaryWidget;
use App\Services\TuitionInvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListTuitionInvoices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TuitionInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_monthly_invoices')
                ->label('Tạo hóa đơn tháng')
                ->icon('heroicon-m-document-plus')
                ->color('success')
                ->schema([
                    TextInput::make('month')
                        ->label('Tháng')
                        ->type('month')
                        ->required()
                        ->default(now()->format('Y-m')),
                ])
                ->action(function (array $data) {
                    $result = app(TuitionInvoiceService::class)->generateMonthlyInvoices($data['month']);

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title('Lỗi')
                            ->body($result->getMessage())
                            ->send();

                        throw new Halt();
                    }

                    Notification::make()
                        ->success()
                        ->title($result->getMessage())
                        ->send();

                    $this->dispatch('refresh');
                }),
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
