<?php

namespace App\Filament\Resources\Money;

use App\Filament\Resources\Money\Pages\CreateMoney;
use App\Filament\Resources\Money\Pages\EditMoney;
use App\Filament\Resources\Money\Pages\ListMoney;
use App\Filament\Resources\Money\Pages\StatsMoney;
use App\Filament\Resources\Money\Schemas\MoneyForm;
use App\Filament\Resources\Money\Tables\MoneyTable;
use App\Models\Money;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MoneyResource extends Resource
{
    protected static ?string $model = Money::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|null|\UnitEnum $navigationGroup = 'Деньги';

    protected static ?string $navigationLabel = 'Money';

    protected static ?string $recordTitleAttribute = 'project';

    public static function form(Schema $schema): Schema
    {
        return MoneyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MoneyTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMoney::route('/'),
            'stats' => StatsMoney::route('/stats'),
            'create' => CreateMoney::route('/create'),
            'edit' => EditMoney::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make()
                ->label('Операции')
                ->icon(static::$navigationIcon)
                ->group(static::$navigationGroup)
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index'))
                ->url(static::getUrl('index')),
            NavigationItem::make()
                ->label('Статистика')
                ->icon(Heroicon::OutlinedChartBar)
                ->group(static::$navigationGroup)
                ->sort(2)
                ->badge(static fn () => StatsMoney::getNavigationBadge())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.stats'))
                ->url(static::getUrl('stats')),
        ];
    }

}
