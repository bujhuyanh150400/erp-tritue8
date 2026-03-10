<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum ScheduleType: int
{
    use EnumHelper;
    case Main    = 0; // Lịch chính
    case Makeup  = 1; // Học bù
    case Extra   = 2; // Tăng cường
    case Holiday = 3; // Nghỉ lễ

    public function label(): string
    {
        return match($this) {
            self::Main    => 'Lịch chính',
            self::Makeup  => 'Học bù',
            self::Extra   => 'Tăng cường',
            self::Holiday => 'Nghỉ lễ',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Main    => 'blue',
            self::Makeup  => 'orange',
            self::Extra   => 'purple',
            self::Holiday => 'red',
        };
    }
}
