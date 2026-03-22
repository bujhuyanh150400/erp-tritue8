<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Contracts\HasLabel;

enum ClassStatus: int implements HasLabel
{
    use EnumHelper;

    case Active    = 0;
    case Suspended = 1;
    case Ended     = 2;

    public function label(): string
    {
        return match($this) {
            self::Active    => 'Đang hoạt động',
            self::Suspended => 'Tạm ngưng',
            self::Ended     => 'Kết thúc',
        };
    }

}
