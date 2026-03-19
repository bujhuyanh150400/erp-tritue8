<?php

use Illuminate\Support\Facades\Route;

// Tạo route mặc định để redirect đến dashboard
Route::get('/', function () {
    return redirect()->to(\Filament\Pages\Dashboard::getUrl());
});
