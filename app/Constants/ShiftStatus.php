<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum ShiftStatus: int
{
    use EnumHelper;

    case Open = 0; // Đang làm
    case Confirmed = 1; // Đã xác nhận
    case Locked = 2; // Đã khóa (sau chốt lương)

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang làm',
            self::Confirmed => 'Đã xác nhận',
            self::Locked => 'Đã khóa',
        };
    }
}
