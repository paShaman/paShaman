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
                TextColumn::make('year')
                    ->size('xs')
                    ->numeric(),
                TextColumn::make('month')
                    ->size('xs')
                    ->numeric(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'site' => 'primary',
                        'salary' => 'warning',
                        'bank' => 'info',
                        'other' => 'success',
                        'delta' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sum')
                    ->size('xs')
                    ->alignRight()
                    ->numeric(),
                TextColumn::make('project')
                    ->size('xs')
                    ->searchable(),
                TextColumn::make('date_payed')
                    ->size('xs')
                    ->date(),
                IconColumn::make('is_payed')
                    ->size('sm')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'plan' => 'info',
                        'active' => 'primary',
                        'cancel' => 'danger',
                        'finish' => 'gray',
                        'payed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('comment')
                    ->size('xs')
                    ->searchable(),
            ])
            ->extraAttributes([
                // Добавляем кастомный класс именно для этой таблицы
                'class' => 'compact-table-rows',
            ])
            ->recordClasses(function ($record) {
                return match($record->status) {
                    'finish'    => 'table-row-accent fi-color fi-color-warning fi-bg-color-50',
                    'active'    => 'table-row-accent fi-color fi-color-success fi-bg-color-50',
                    default     => null,
                };
            })
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
            ])
            ->defaultPaginationPageOption(25)
        ;
    }
}
