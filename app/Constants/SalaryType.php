<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum SalaryType: int
{
    use EnumHelper;
    case Hourly = 0; // Theo giờ
    case Fixed  = 1; // Cố định tháng

    public function label(): string
    {
        return match($this) {
            self::Hourly => 'Theo giờ',
            self::Fixed  => 'Cố định',
        };
    }
}
