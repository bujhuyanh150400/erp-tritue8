<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum ScheduleChangeStatus: int
{
    use EnumHelper;
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
        };
    }
}
