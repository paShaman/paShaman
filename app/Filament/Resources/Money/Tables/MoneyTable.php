<?php

namespace App\Filament\Resources\Money\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MoneyTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('month')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('sum')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date_payed')
                    ->date()
                    ->sortable(),
                TextColumn::make('is_payed')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
