<?php

namespace App\Providers\Filament;


use App\Filament\Auth\Login;
use App\Filament\Pages\ScheduleCalendar\ScheduleCalendar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
                'blue'    => Color::Blue,
                'orange'  => Color::Orange,
                'purple'  => Color::Purple,
                'red'     => Color::Red,
            ])
            ->brandLogo(new HtmlString('
               <div style="display: flex; align-items: center; gap: 8px;">
                    <img src="'.asset('assets/images/logo.png').'" style="height: 3rem;">
                    <span style="font-size: 1.25rem; font-weight: bold; color: var(--brand-text-color);">
                        '.config('app.name').'
                    </span>
                </div>
                <style>
                    /* Tự định nghĩa biến màu dựa trên class .dark của Filament */
                    :root { --brand-text-color: #111827; }
                    .dark { --brand-text-color: #fbbf24; } /* Màu amber-400 cho dark mode */
                </style>
            '))
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                ScheduleCalendar::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->navigationItems([
                NavigationItem::make("Log hệ thống")
                    ->url(url('log-viewer'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::DocumentMagnifyingGlass),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js'])"),
            )
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa()
            ->plugins([
                FilamentFullCalendarPlugin::make()
                    ->selectable()
                    ->editable()
                    ->timezone(config('app.timezone')),
            ]);
    }
}
