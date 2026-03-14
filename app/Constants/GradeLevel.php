<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum GradeLevel: int
{
    use EnumHelper;
    case Grade0 = 0;
    case Grade1 = 1;
    case Grade2 = 2;
    case Grade3 = 3;
    case Grade4 = 4;
    case Grade5 = 5;
    case Grade6 = 6;
    case Grade7 = 7;
    case Grade8 = 8;
    case Grade9 = 9;
    case Grade10 = 10;
    case Grade11 = 11;
    case Grade12 = 12;

    public function label(): string
    {
        return match ($this) {
            self::Grade0 => 'Tiền tiểu học',
            self::Grade1 => 'Lớp 1',
            self::Grade2 => 'Lớp 2',
            self::Grade3 => 'Lớp 3',
            self::Grade4 => 'Lớp 4',
            self::Grade5 => 'Lớp 5',
            self::Grade6 => 'Lớp 6',
            self::Grade7 => 'Lớp 7',
            self::Grade8 => 'Lớp 8',
            self::Grade9 => 'Lớp 9',
            self::Grade10 => 'Lớp 10',
            self::Grade11 => 'Lớp 11',
            self::Grade12 => 'Lớp 12',
        };
    }
}
