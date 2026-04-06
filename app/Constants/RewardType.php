<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum RewardType: int
{
    use EnumHelper;

    case Physical = 1; // Phần thưởng vật chất (bút, vở...)
    case Privilege = 2; // Đặc quyền (miễn kiểm tra miệng...)
    case Discount = 3; // Giảm học phí / ưu đãi tài chính

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Vật phẩm',
            self::Privilege => 'Đặc quyền',
            self::Discount => 'Giảm học phí',
        };
    }
}
