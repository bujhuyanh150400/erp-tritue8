<?php

namespace App\Filament\Resources\TuitionInvoices\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\TuitionInvoices\TuitionInvoiceResource;
use App\Models\TuitionInvoice;
use App\Services\TuitionInvoiceService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewTuitionInvoice extends ViewRecord
{
    protected static string $resource = TuitionInvoiceResource::class;
    protected string $view = 'filament.resources.tuition-invoices.pages.view-tuition-invoice';

    public array $detailData = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $result = app(TuitionInvoiceService::class)->getStudentMonthlyInvoiceDetail($this->getRecord());

        abort_unless($result->isSuccess(), 404, $result->getMessage());

        $this->detailData = $result->getData();
    }

    public function getTitle(): string | Htmlable
    {
        $month = $this->detailData['month'] ?? $this->getRecord()->month;

        return 'Chi tiết học phí tháng ' . $month;
    }

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
        ];
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->getRecord(),
            'detailData' => $this->detailData,
        ];
    }

    /**
     * @return TuitionInvoice
     */
    public function getRecord(): TuitionInvoice
    {
        /** @var TuitionInvoice $record */
        $record = parent::getRecord();

        return $record;
    }
}
