<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum Gender: int
{
    use EnumHelper;

    case Male = 0;
    case Female = 1;
    case Other = 2;

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Nam',
            self::Female => 'Nữ',
            self::Other => 'Khác',
        };
    }
}
