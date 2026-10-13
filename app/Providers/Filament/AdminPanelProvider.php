<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsIconAlias;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Administrators only: dedicated guard and password broker,
            // unrelated to mobile users (decision 0009).
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->login()
            ->brandName('Allo Vaho')
            ->brandLogo(fn (): string => asset('images/logo-allo-vaho.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): string => asset('images/logo-allo-vaho.png'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Fonts are bundled with the application: nothing is loaded from a CDN.
            ->font(
                'Noto Sans',
                url: fn (): string => Vite::asset('resources/css/admin/fonts.css'),
                provider: LocalFontProvider::class,
            )
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors($this->colors())
            ->icons($this->icons())
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Palettes derived from the --av-* tokens of the validated mockups
     * (resources/css/admin/tokens.css).
     *
     * @return array<string, array<int, string>>
     */
    protected function colors(): array
    {
        return [
            'primary' => Color::hex('#0077B6'),
            'info' => Color::hex('#009FE3'),
            'success' => Color::hex('#22A060'),
            'warning' => Color::hex('#F2A93B'),
            'danger' => Color::hex('#B8342B'),
            // Warm neutrals (sand to ink) instead of a cold gray.
            'gray' => [
                50 => '#FAF7F2',
                100 => '#F4F1EB',
                200 => '#E6E0D6',
                300 => '#D3CBBE',
                400 => '#A79F92',
                500 => '#807869',
                600 => '#6E665A',
                700 => '#47423B',
                800 => '#2C2823',
                900 => '#1D1A17',
                950 => '#141210',
            ],
        ];
    }

    /**
     * Lucide is the icon set of the validated mockups: the icons of the panel
     * chrome are swapped for their Lucide counterparts.
     *
     * @return array<string, string>
     */
    protected function icons(): array
    {
        return [
            PanelsIconAlias::GLOBAL_SEARCH_FIELD => 'lucide-search',
            PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => 'lucide-layout-dashboard',
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => 'lucide-panel-left-close',
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => 'lucide-panel-left-open',
            PanelsIconAlias::SIDEBAR_GROUP_COLLAPSE_BUTTON => 'lucide-chevron-up',
            PanelsIconAlias::THEME_SWITCHER_LIGHT_BUTTON => 'lucide-sun',
            PanelsIconAlias::THEME_SWITCHER_DARK_BUTTON => 'lucide-moon',
            PanelsIconAlias::THEME_SWITCHER_SYSTEM_BUTTON => 'lucide-monitor',
            PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON => 'lucide-menu',
            PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON => 'lucide-x',
            PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON => 'lucide-bell',
            PanelsIconAlias::USER_MENU_PROFILE_ITEM => 'lucide-circle-user',
            PanelsIconAlias::USER_MENU_LOGOUT_BUTTON => 'lucide-log-out',
        ];
    }
}
