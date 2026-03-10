<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum AttendanceStatus: int
{
    use EnumHelper;

    case Present = 0; // Có mặt
    case Late = 1; // Đi muộn
    case AbsentExcused = 2; // Vắng có phép
    case Absent = 3; // Vắng không phép

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Có mặt',
            self::Late => 'Đi muộn',
            self::AbsentExcused => 'Vắng có phép',
            self::Absent => 'Vắng không phép',
        };
    }

    public function isFeeCountable(): bool
    {
        return match ($this) {
            self::Present, self::Late => true,
            default => false,
        };
    }
}
