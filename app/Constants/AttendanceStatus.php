<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: int implements HasLabel
{
    use EnumHelper;

    case Present = 0; // Có mặt
    case Late = 1; // Đi muộn
    case AbsentExcused = 2; // Vắng có phép
    case Absent = 3; // Vắng không phép

    case Draft = 4; // Bản ghi chấm danh chờ xác nhận (ko có điểm danh)

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Có mặt',
            self::Late => 'Đi muộn',
            self::AbsentExcused => 'Vắng có phép',
            self::Absent => 'Vắng không phép',
            self::Draft => 'Chưa điểm danh',
        };
    }

    public function colorFilament()
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::AbsentExcused => 'danger',
            self::Absent => 'danger',
            self::Draft => 'gray',
            default => 'gray',
        };
    }


    /**
     * Lấy danh sách các trạng thái có mặt trong buổi học
     * @return AttendanceStatus[]
     */
    public static function presentStatus(): array
    {
        return [self::Present, self::Late];
    }
}
