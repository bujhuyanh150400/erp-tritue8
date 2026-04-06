<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use Filament\Actions\Action;

class UpdateScheduleInstanceAction
{

    public static function make()
    {
        return Action::make('update_schedule_instance')
            ->label('Sửa lịch học')
            ->color('info')
            ->icon('heroicon-m-calendar-days');
    }
}
