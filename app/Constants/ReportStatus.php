<?php

namespace App\Constants;

enum ReportStatus: int
{
    case Pending   = 0;
    case Submitted = 1;
    case Approved  = 2;
    case Rejected  = 3;

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Chưa nộp',
            self::Submitted => 'Đã nộp chờ duyệt',
            self::Approved  => 'Đã duyệt',
            self::Rejected  => 'Từ chối',
        };
    }
}
