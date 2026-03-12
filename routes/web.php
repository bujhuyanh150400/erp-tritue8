<?php

use App\Http\Controllers\AuthController;
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
        Route::get('/register', [UserController::class, 'registerView'])->name('register');
        Route::post('/register', [UserController::class, 'registerUser'])->name('register.post');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        // Student
        Route::get('/v1/student/list', [StudentController::class, 'listStudent'])->name('student.list');
        Route::post('/v1/student/create', [StudentController::class, 'createStudent'])->name('student.create');
        Route::post('/v1/student/{id}/update', [StudentController::class, 'updateStudent'])->name('student.update');
        Route::delete('/v1/student/{id}/delete', [StudentController::class, 'deletedStudent']) ->name('student.delete');

        // Teacher


        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        });
    });
});
