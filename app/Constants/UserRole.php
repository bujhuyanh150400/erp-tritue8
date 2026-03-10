<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum UserRole: int
{
    use EnumHelper;

    case Admin = 0;
    case Teacher = 1;
    case Staff = 2;
    case Student = 3;

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Teacher => 'Giáo viên',
            self::Staff => 'Nhân viên',
            self::Student => 'Học sinh - phụ huynh',
        };
    }
}
