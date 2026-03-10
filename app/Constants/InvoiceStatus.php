<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum InvoiceStatus: int
{
    use EnumHelper;

    case Unpaid = 0; // Chưa thanh toán
    case PartiallyPaid = 1; // Thanh toán một phần
    case Paid = 2; // Đã thanh toán
    case Cancelled = 3; // Đã hủy

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::PartiallyPaid => 'Thanh toán một phần',
            self::Paid => 'Đã thanh toán',
            self::Cancelled => 'Đã hủy',
        };
    }
}
