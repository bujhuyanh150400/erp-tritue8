<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;

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
            'middleware' => ['check-role:admin']
        ], function () {
            Route::get('/list', [StudentController::class, 'listStudent'])->name('student.list');
            Route::get('/create', [StudentController::class, 'viewCreate'])->name('student.create');
            Route::post('/create', [StudentController::class, 'createStudent'])->name('student.create');
            Route::post('/{id}/update', [StudentController::class, 'updateStudent'])->name('student.update');
            Route::put('/{id}/disabled', [StudentController::class, 'disabledStudent'])->name('student.disabled');
        });

        Route::group([
            'prefix' => 'teacher',
            'middleware' => ['check-role:admin']
        ], function () {
            Route::get('/list', [TeacherController::class, 'listTeacher'])->name('teacher.list');
            Route::get('/create', [TeacherController::class, 'viewCreate'])->name('teacher.create');
            Route::post('/create', [TeacherController::class, 'createTeacher'])->name('teacher.create');
            Route::post('/{id}/disabled', [TeacherController::class, 'updateTeacher'])->name('teacher.update');
        });
    });
});
