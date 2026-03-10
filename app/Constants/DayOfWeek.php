<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum DayOfWeek: int
{
    use EnumHelper;

    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Thứ 2',
            self::Tuesday => 'Thứ 3',
            self::Wednesday => 'Thứ 4',
            self::Thursday => 'Thứ 5',
            self::Friday => 'Thứ 6',
            self::Saturday => 'Thứ 7',
            self::Sunday => 'Chủ nhật',
        };
    }
}
