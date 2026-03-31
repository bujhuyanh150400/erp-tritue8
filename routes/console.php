<?php

use Illuminate\Support\Facades\Schedule;



// Lên lịch chạy cuốn chiếu: 2 giờ sáng mỗi Chủ Nhật
Schedule::command('schedule:rolling-generate')
    ->weeklyOn(0, '02:00'); // 2 giờ sáng mỗi Chủ Nhật

// Xóa dữ liệu Telescope sau 48 giờ
Schedule::command('telescope:prune --hours=48')->daily();
