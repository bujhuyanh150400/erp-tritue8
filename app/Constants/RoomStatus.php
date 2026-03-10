<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum RoomStatus: int
{
    use EnumHelper;

    case Active = 0;
    case Locked = 1;
    case Maintenance = 2;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::Locked => 'Tạm khóa',
            self::Maintenance => 'Bảo trì',
        };
    }
}
