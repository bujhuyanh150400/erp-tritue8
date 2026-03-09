<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'dashboard')->name('home');
Route::inertia('/login', 'login')->name('home');
