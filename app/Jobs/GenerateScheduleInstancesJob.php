<?php

namespace App\Jobs;

use App\Models\ClassScheduleTemplate;
use App\Services\ClassScheduleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tạo lịch học từ lịch cố định (Template) cho lớp học
 */
class GenerateScheduleInstancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ClassScheduleTemplate $template,
        public string $startDate,
        public string $endDate
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ClassScheduleService $scheduleService): void
    {
        // 1. Kiểm tra nếu Template đã bị xóa hoặc kết thúc
        if ($this->template->end_date && $this->startDate > $this->template->end_date) {
            return;
        }

        // 2. Chốt ngày sinh kết thúc (không vượt quá hạn của Template)
        $realEndDate = $this->endDate;
        if ($this->template->end_date && $this->endDate > $this->template->end_date) {
            $realEndDate = $this->template->end_date;
        }
        // 3. Thực thi sinh lịch
        $scheduleService->generateInstances(
           template:  $this->template,
            startDate:  $this->startDate,
            endDate:  $realEndDate
        );
    }
}
