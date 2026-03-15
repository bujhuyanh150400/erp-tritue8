<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/dashboard');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AuthController::class, 'loginView'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        });

        Route::group([
            'prefix' => 'student',
            'middleware' => ['check-role:admin'],
        ], function () {
            Route::get('/list', [StudentController::class, 'listStudent'])->name('student.list');
            Route::get('/create', [StudentController::class, 'viewCreate'])->name('student.view_create');
            Route::post('/create', [StudentController::class, 'create'])->name('student.create');
            Route::get('/{id}/update', [StudentController::class, 'viewUpdate'])->name('student.view_update');
            Route::put('/{id}/update', [StudentController::class, 'update'])->name('student.update');
        });

        Route::group([
            'prefix' => 'teacher',
            'middleware' => ['check-role:admin'],
        ], function () {
            Route::get('/list', [TeacherController::class, 'listTeacher'])->name('teacher.list');
            Route::get('/create', [TeacherController::class, 'viewCreate'])->name('teacher.create');
            Route::post('/create', [TeacherController::class, 'createTeacher'])->name('teacher.create');
            Route::post('/{id}/disabled', [TeacherController::class, 'updateTeacher'])->name('teacher.update');
            Route::get('/{id}/update', [StudentController::class, 'viewUpdate'])->name('teacher.view_update');
        });

        Route::prefix('staff')
            ->middleware(['check-role:admin'])
            ->group(function () {
                Route::get('/list', [StaffController::class, 'listStaff'])->name('staff.list');
                Route::get('/create', [StaffController::class, 'viewCreate'])->name('staff.view_create');
                Route::post('/create', [StaffController::class, 'createStaff'])->name('staff.create');
                Route::get('/{id}/update', [StaffController::class, 'viewUpdate'])->name('staff.view_update');
                Route::put('/{id}/update', [StaffController::class, 'updateStaff'])->name('staff.update');
                Route::delete('/{id}/delete', [StaffController::class, 'deleteStaff'])->name('staff.delete');
            });

        Route::prefix('subject')->middleware(['check-role:admin'])->group(function () {

            Route::get('/list', [SubjectController::class, 'listSubject'])->name('subject.list');
            Route::get('/create', [SubjectController::class, 'viewCreate'])->name('subject.view_create');
            Route::post('/create', [SubjectController::class, 'create'])->name('subject.create');
            Route::get('/{id}/update', [SubjectController::class, 'viewUpdate'])->name('subject.view_update');
            Route::put('/{id}/update', [SubjectController::class, 'update'])->name('subject.update');
            Route::post('/{id}/delete', [SubjectController::class, 'delete'])->name('subject.delete');
        });

        Route::prefix('room')->middleware(['check-role:admin'])->group(function () {

            Route::get('/list', [RoomController::class,'listRoom'])->name('room.list');

            Route::get('/create', [RoomController::class,'viewCreate'])->name('room.view_create');
            Route::post('/create', [RoomController::class,'create'])->name('room.create');

            Route::get('/{id}/update', [RoomController::class,'viewUpdate'])->name('room.view_update');
            Route::put('/{id}/update', [RoomController::class,'update'])->name('room.update');

            Route::post('/{id}/delete', [RoomController::class,'delete'])->name('room.delete');
        });

    });
});
