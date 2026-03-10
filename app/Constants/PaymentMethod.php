<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum PaymentMethod: int
{
    use EnumHelper;

    case Cash         = 0;
    case BankTransfer = 1;

    public function label(): string
    {
        return match($this) {
            self::Cash         => 'Tiền mặt',
            self::BankTransfer => 'Chuyển khoản',
        };
    }
}
