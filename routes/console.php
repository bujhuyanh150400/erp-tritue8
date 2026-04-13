<?php

use Illuminate\Support\Facades\Schedule;



// Lên lịch chạy cuốn chiếu
Schedule::command('app:rolling-generate')
    ->at("23:00") // 23 giờ tối mỗi ngày
    ->withoutOverlapping() // RẤT QUAN TRỌNG: Chặn chạy chồng chéo nếu Job trước chưa xong
    ->runInBackground();   // Giải phóng terminal nhanh hơn

