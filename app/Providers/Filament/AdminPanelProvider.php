<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckAdminIp;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\TrackAdminSession;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->brandName('Wameed POS')
            ->colors([
                // Thawani Brand: Primary Orange (#FD8209)
                'primary' => Color::hex('#FD8209'),
                'gray' => Color::Slate,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->font('Cairo')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth('full')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('nav.group_core')),
                NavigationGroup::make()->label(fn () => __('nav.group_business')),
                NavigationGroup::make()->label(fn () => __('nav.group_people')),
                NavigationGroup::make()->label(fn () => __('nav.group_support')),
                NavigationGroup::make()->label(fn () => __('nav.group_content')),
                NavigationGroup::make()->label(fn () => __('nav.group_integrations')),
                NavigationGroup::make()->label(fn () => __('nav.group_notifications')),
                NavigationGroup::make()->label(fn () => __('nav.group_updates')),
                NavigationGroup::make()->label(fn () => __('nav.group_security')),
                NavigationGroup::make()->label(fn () => __('nav.group_settings')),
                NavigationGroup::make()->label(fn () => __('nav.group_zatca')),
                NavigationGroup::make()->label(fn () => __('nav.group_analytics')),
                NavigationGroup::make()->label(fn () => __('nav.group_infrastructure')),
                NavigationGroup::make()->label(fn () => __('nav.group_ui_management')),
                NavigationGroup::make()->label(fn () => __('nav.group_website')),
                NavigationGroup::make()->label(fn () => __('nav.group_subscription_billing')),
                NavigationGroup::make()->label(fn () => __('nav.group_ai')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => Blade::render('<livewire:admin-quick-nav /><livewire:admin-locale-switcher />'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                CheckAdminIp::class,
                SetAdminLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                TrackAdminSession::class,
            ]);
    }
}
