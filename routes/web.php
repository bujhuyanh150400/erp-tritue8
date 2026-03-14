<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentController;
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
        });
    });
});
