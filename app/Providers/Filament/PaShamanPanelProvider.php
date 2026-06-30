<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Widgets\MoneyChartWidget;
use App\Filament\Widgets\MoneyStatsWidget;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use AchyutN\FilamentLogViewer\FilamentLogViewer;
use Illuminate\Support\HtmlString;

class PaShamanPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('paShaman')
            ->path('paShaman')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->favicon(asset('images/favicon.svg'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                MoneyStatsWidget::class,
                MoneyChartWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ->navigationItems([
                NavigationItem::make('Основной сайт')
                    ->url('https://pashaman.dev/')
                    ->icon('heroicon-o-globe-alt')
                    ->group('Сайт')
                    ->openUrlInNewTab(),
            ])
            ->navigationGroups([
                'Сайт',
                'Деньги',
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentLogViewer::make()
            ])
            ->renderHook(
                'panels::styles.after',
                fn () => new HtmlString('
                    <style>
                        /* Сжимаем строки только внутри таблицы с классом .compact-table-rows */
                        .compact-table-rows .fi-ta-cell:has(.fi-ta-actions), 
                        .compact-table-rows .fi-ta-cell.fi-ta-selection-cell, 
                        .compact-table-rows .fi-ta-text:not(.fi-inline),
                        .compact-table-rows .fi-ta-icon:not(.fi-inline),
                        .compact-table-rows .fi-ta-header-cell
                        {
                            padding-block: .25rem;
                        }
                        
                        .table-row-accent {
                            background-color: var(--bg);
                        }
                    </style>
                '),
            )
        ;
    }
}
