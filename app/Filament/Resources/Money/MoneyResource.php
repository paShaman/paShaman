<?php

namespace App\Filament\Resources\Money;

use App\Filament\Resources\Money\Pages\CreateMoney;
use App\Filament\Resources\Money\Pages\EditMoney;
use App\Filament\Resources\Money\Pages\ListMoney;
use App\Filament\Resources\Money\Schemas\MoneyForm;
use App\Filament\Resources\Money\Tables\MoneyTable;
use App\Models\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MoneyResource extends Resource
{
    protected static ?string $model = Money::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
            'create' => CreateMoney::route('/create'),
            'edit' => EditMoney::route('/{record}/edit'),
        ];
    }
}
