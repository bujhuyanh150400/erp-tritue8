<?php

namespace App\Providers;

use App\Services\AuthService;
use App\Services\RoomService;
use App\Services\StaffService;
use App\Services\StudentService;
use App\Services\SubjectService;
use App\Services\TeacherService;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton(AuthService::class, AuthService::class);
        $this->app->singleton(RoomService::class, RoomService::class);
        $this->app->singleton(StaffService::class, StaffService::class);
        $this->app->singleton(StudentService::class, StudentService::class);
        $this->app->singleton(TeacherService::class, TeacherService::class);
        $this->app->singleton(SubjectService::class, SubjectService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
