<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum RewardType: int
{
    use EnumHelper;

    case Physical = 0; // Phần thưởng vật chất (bút, vở...)
    case Privilege = 1; // Đặc quyền (miễn kiểm tra miệng...)
    case Discount = 2; // Giảm học phí

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Vật phẩm',
            self::Privilege => 'Đặc quyền',
            self::Discount => 'Giảm học phí',
        };
    }
}
