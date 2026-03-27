<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Contracts\HasLabel;

enum ScheduleStatus: int implements HasLabel
{
    use EnumHelper;

    case Upcoming = 0; // Chưa diễn ra
    case Completed = 1; // Đã diễn ra
    case Cancelled = 2; // Đã hủy
    case Rescheduled = 3; // Đã dời

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Chưa diễn ra',
            self::Completed => 'Đã diễn ra',
            self::Cancelled => 'Đã hủy',
            self::Rescheduled => 'Đã dời',
        };
    }
}
