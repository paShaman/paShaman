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
                    ->searchable(),
                TextColumn::make('year')
                    ->numeric(),
                TextColumn::make('month')
                    ->numeric(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'site' => 'primary',
                        'salary' => 'warning',
                        'bank' => 'info',
                        'other' => 'gray',
                        'delta' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sum')
                    ->numeric(),
                TextColumn::make('project')
                    ->searchable(),
                TextColumn::make('date_payed')
                    ->date(),
                IconColumn::make('is_payed')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'plan' => 'gray',
                        'active' => 'primary',
                        'cancel' => 'danger',
                        'finish' => 'warning',
                        'payed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('comment')
                    ->searchable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw(
                'CASE WHEN `status` = \'cancel\' THEN 1 ELSE 0 END ASC, `is_payed` ASC, `status` ASC, `year` DESC, `month` DESC, `id` DESC'
            ))
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
