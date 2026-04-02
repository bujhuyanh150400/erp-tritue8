<?php

namespace App\Filament\Auth;

use App\Services\AuthService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;

class Login extends BaseLogin
{
    protected AuthService $authService;

    public function boot()
    {
        $this->authService = app(AuthService::class);
    }

    public function getView(): string
    {
        return 'filament.pages.auth.login';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('username')
                ->label("Tên đăng nhập")
                ->string()
                ->required()
                ->autocomplete('username')
                ->extraInputAttributes(['tabindex' => 1])
                ->validationMessages([
                    'required' => "Vui lòng nhập tên đăng nhập",
                ]),
            TextInput::make('password')
                ->label("Mật khẩu")
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->autocomplete('current-password')
                ->required()
                ->extraInputAttributes(['tabindex' => 2])
                ->validationMessages([
                    'required' => "Vui lòng nhập mật khẩu",
                ]),
            Checkbox::make('remember')
                ->label("Ghi nhớ đăng nhập")
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::auth/pages/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => $exception->minutesUntilAvailable,
                ]))
                ->body(array_key_exists('body', __('filament-panels::auth/pages/login.notifications.throttled') ?: []) ? __('filament-panels::auth/pages/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => $exception->minutesUntilAvailable,
                ]) : null)
                ->danger()
                ->send();
        }
        $data = $this->form->getState();

        $result = $this->authService->handleLogin($data['username'], $data['password'], $data['remember']);

        if ($result->isError()) {
            Notification::make()
                ->title($result->getMessage())
                ->danger()
                ->send();
        }
        return app(LoginResponse::class);
    }
}
