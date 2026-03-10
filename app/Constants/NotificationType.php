<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;

enum NotificationType: int
{
    use EnumHelper;

    case General      = 0; // Thông báo chung
    case Class        = 1; // Thông báo lớp
    case Urgent       = 2; // Thông báo khẩn
    case Reminder     = 3; // Nhắc nhở
    case Homework     = 4; // BTVN
    case Schedule     = 5; // Lịch học
    case Tuition      = 6; // Học phí

    public function label(): string
    {
        return match($this) {
            self::General  => 'Thông báo chung',
            self::Class    => 'Thông báo lớp',
            self::Urgent   => 'Thông báo khẩn',
            self::Reminder => 'Nhắc nhở',
            self::Homework => 'Bài tập về nhà',
            self::Schedule => 'Lịch học',
            self::Tuition  => 'Học phí',
        };
    }
}
