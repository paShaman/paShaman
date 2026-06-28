<?php

namespace App\Filament\Resources\Money\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MoneyTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('month')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'site' => 'primary',
                        'salary' => 'warning',
                        'bank' => 'info',
                        'other' => 'gray',
                        'delta' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('sum')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_payed')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_payed')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'plan' => 'gray',
                        'active' => 'primary',
                        'cancel' => 'danger',
                        'finish' => 'warning',
                        'payed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('comment')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
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
