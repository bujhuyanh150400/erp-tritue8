<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Filament\Pages\ScheduleCalendar\Components\CreateExtraScheduleTable;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreateExtraScheduleAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Lịch tăng cường')
            ->icon(Heroicon::PlusCircle)
            ->color('success')
            ->modalHeading('Tạo lịch học tăng cường')
            ->modalDescription("Lựa chọn học sinh tham gia lịch tăng cường")
            ->modalWidth(Width::FiveExtraLarge)
            ->modalFooterActions(fn () => [])
            ->modalContent(fn () => new HtmlString(
                Blade::render('@livewire("' . CreateExtraScheduleTable::class . '")')
            ));
    }
}
