<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum NotificationSendStatus: int
{
    use EnumHelper;

    case Pending = 0;
    case Sent = 1;
    case Failed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ gửi',
            self::Sent => 'Đã gửi',
            self::Failed => 'Thất bại',
        };
    }
}
