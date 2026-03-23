<?php

namespace App\Filament\Resources\Classes\Components;

use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class AddStudentToClassAction
{
    /**
     * Tạo action để thêm học sinh vào lớp
     * @return Action
     */
    public static function make()
    {
        return Action::make('add_student')
            ->label('Thêm học sinh')
            ->icon(Heroicon::UserPlus)
            ->color('info')
            ->modalHeading('Đăng ký học sinh vào lớp')
            ->modalDescription("Hãy tích chọn học sinh muốn đăng ký vào lớp.")
            ->modalWidth(Width::Full)
            ->modalFooterActions(fn () => [])
            ->modalContent(fn (SchoolClass $record) => new HtmlString(
                Blade::render(
                    '@livewire("' . AddStudentToClassTable::class . '", ["classId" => $classId])',
                    ['classId' => $record->id]
                )
            ));
    }
}
