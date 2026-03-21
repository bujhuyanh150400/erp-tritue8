<?php

namespace App\Constants;

enum ClassStatus: int
{
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

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->toArray();
    }
}
