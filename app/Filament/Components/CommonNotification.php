<?php

namespace App\Filament\Components;

use Filament\Notifications\Notification;

class CommonNotification
{
    public static function success(): Notification
    {
        return Notification::make()
            ->success()
            ->color('success');
    }

    public static function error(): Notification
    {
        return Notification::make()
            ->danger()
            ->color('red');
    }

    public static function warning(): Notification
    {
        return Notification::make()
            ->warning()
            ->color('yellow');
    }

    public static function info(): Notification
    {
        return Notification::make()
            ->info()
            ->color('blue');
    }
}
