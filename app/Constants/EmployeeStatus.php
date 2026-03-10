<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum EmployeeStatus: int
{
    use EnumHelper;

    case Active = 0;
    case Inactive = 1;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang làm việc',
            self::Inactive => 'Đã nghỉ',
        };
    }
}
