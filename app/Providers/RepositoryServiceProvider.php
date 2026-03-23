<?php

namespace App\Providers;


use App\Repositories\ClassEnrollmentRepository;
use App\Repositories\ClassRepository;
use App\Repositories\ClassScheduleTemplateRepository;
use App\Repositories\RoomRepository;
use App\Repositories\ScheduleInstanceRepository;
use App\Repositories\StaffRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
