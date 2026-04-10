<?php

namespace App\Providers;


use App\Repositories\AttendanceRecordRepository;
use App\Repositories\AttendanceSessionRepository;
use App\Repositories\ClassEnrollmentRepository;
use App\Repositories\ClassRepository;
use App\Repositories\ClassScheduleTemplateRepository;
use App\Repositories\MonthlyReportRepository;
use App\Repositories\RewardItemRepository;
use App\Repositories\RewardPointRepository;
use App\Repositories\RewardRedemptionRepository;
use App\Repositories\RoomRepository;
use App\Repositories\ScheduleInstanceRepository;
use App\Repositories\ScoreRepository;
use App\Repositories\StaffRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TeacherSalaryConfigRepository;
use App\Repositories\TuitionInvoiceLogRepository;
use App\Repositories\TuitionInvoiceRepository;
use App\Repositories\UserLogRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, UserRepository::class);
        $this->app->singleton(UserLogRepository::class, UserLogRepository::class);
        $this->app->singleton(TeacherRepository::class, TeacherRepository::class);
        $this->app->singleton(StudentRepository::class, StudentRepository::class);
        $this->app->singleton(SubjectRepository::class, SubjectRepository::class);
        $this->app->singleton(StaffRepository::class, StaffRepository::class);
        $this->app->singleton(RoomRepository::class, RoomRepository::class);
        $this->app->singleton(ClassRepository::class, ClassRepository::class);
        $this->app->singleton(ClassScheduleTemplateRepository::class, ClassScheduleTemplateRepository::class);
        $this->app->singleton(ClassEnrollmentRepository::class, ClassEnrollmentRepository::class);
        $this->app->singleton(ScheduleInstanceRepository::class, ScheduleInstanceRepository::class);
        $this->app->singleton(TeacherSalaryConfigRepository::class, TeacherSalaryConfigRepository::class);
        $this->app->singleton(AttendanceSessionRepository::class, AttendanceSessionRepository::class);
        $this->app->singleton(AttendanceRecordRepository::class, AttendanceRecordRepository::class);
        $this->app->singleton(ScoreRepository::class, ScoreRepository::class);
        $this->app->singleton(RewardItemRepository::class, RewardItemRepository::class);
        $this->app->singleton(RewardPointRepository::class, RewardPointRepository::class);
        $this->app->singleton(RewardRedemptionRepository::class, RewardRedemptionRepository::class);
        $this->app->singleton(MonthlyReportRepository::class, MonthlyReportRepository::class);
        $this->app->singleton(TuitionInvoiceRepository::class, TuitionInvoiceRepository::class);
        $this->app->singleton(TuitionInvoiceLogRepository::class, TuitionInvoiceLogRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
