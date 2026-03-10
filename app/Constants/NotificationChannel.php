<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum NotificationChannel: int
{
    use EnumHelper;

    case Zalo = 0;
    case Email = 1;

    public function label(): string
    {
        return match ($this) {
            self::Zalo => 'Zalo',
            self::Email => 'Email',
        };
    }
}
