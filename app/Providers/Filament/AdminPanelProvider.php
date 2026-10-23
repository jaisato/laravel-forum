<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The staff's panel at /admin (spec §4.8): moderation for moderators,
 * plus categories and tags for administrators. Every decision goes through
 * the same actions and policies as the forum and the API.
 */
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // The panel's own strings, kept out of lang/*.json: the interface
        // bundles those files for the browser.
        $this->loadJsonTranslationsFrom(lang_path('admin'));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // No login form of its own: Filament would sign people in with the
            // password alone, skipping Fortify's two-factor challenge and
            // passkeys. Guests are sent to the forum login instead, and
            // User::canAccessPanel() admits staff only.
            ->colors([
                'primary' => Color::Amber,
            ])
            // A check whose policy method is missing fails instead of
            // passing: by default Filament takes a missing method as a yes.
            ->strictAuthorization()
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('Moderation')),
                NavigationGroup::make()->label(fn (): string => __('Forum')),
            ])
            ->userMenuItems([
                Action::make('forum')
                    ->label(fn (): string => __('Back to the forum'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url(fn (): string => route('home')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                // The member's language, as in the forum.
                SetLocale::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
