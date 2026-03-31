<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Contracts\HasLabel;

enum AttendanceSessionStatus: int implements HasLabel
{
    use EnumHelper;

    case Draft = 0; // Bắt đầu
    case Completed = 1; // Hoàn thành
    case Locked = 2; // Đã khóa

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bắt đầu buổi học',
            self::Completed => 'Hoàn thành',
            self::Locked => 'Đã khóa',
        };
    }

    public function colorFilament(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Completed => 'success',
            self::Locked => 'danger',
        };
    }
}
