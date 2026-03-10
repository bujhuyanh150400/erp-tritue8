<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum FeeType: int
{
    use EnumHelper;

    case Normal = 0; // Tính học phí bình thường
    case Free = 1; // Miễn phí
    case Custom = 2; // Học phí riêng

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Bình thường',
            self::Free => 'Miễn phí',
            self::Custom => 'Học phí riêng',
        };
    }
}
