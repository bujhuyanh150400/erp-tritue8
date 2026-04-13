<?php

namespace App\Console\Commands;

use App\Repositories\ClassScheduleTemplateRepository;
use Illuminate\Console\Command;
use App\Jobs\GenerateScheduleInstancesJob;
use Carbon\Carbon;

class RollingScheduleGenerate extends Command
{
    protected $signature = 'app:rolling-generate';

    protected $description = 'Sinh lịch cuốn chiếu cho tuần thứ 5 trong tương lai';

    public function handle(ClassScheduleTemplateRepository $classScheduleTemplateRepository)
    {
        // Nhắm đến tuần thứ 5 tính từ hiện tại (Giữ vùng đệm 4 tuần)
        $targetWeekStart = Carbon::now()->addWeeks(4)->startOfWeek()->toDateString();
        $targetWeekEnd = Carbon::now()->addWeeks(4)->endOfWeek()->toDateString();

        // Tìm các lớp vẫn đang Active và chưa hết hạn
        $activeTemplates = $classScheduleTemplateRepository->getActiveTemplatesForRolling($targetWeekStart);

        $count = 0;
        foreach ($activeTemplates as $template) {
            // Đẩy vào Queue, chia nhỏ khối lượng công việc cho Server
            GenerateScheduleInstancesJob::dispatch(
                $template,
                $targetWeekStart,
                $targetWeekEnd
            );
            $count++;
        }

        $this->info("Đã queue sinh lịch cho {$count} templates trong tuần từ {$targetWeekStart} đến {$targetWeekEnd}.");
        return self::SUCCESS;
    }
}
