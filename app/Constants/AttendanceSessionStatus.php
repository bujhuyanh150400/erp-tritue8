<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum AttendanceSessionStatus: int
{
    use EnumHelper;

    case Draft = 0; // Nháp
    case Completed = 1; // Hoàn thành
    case Locked = 2; // Đã khóa

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Completed => 'Hoàn thành',
            self::Locked => 'Đã khóa',
        };
    }
}
