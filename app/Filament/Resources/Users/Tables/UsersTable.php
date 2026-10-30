<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                ImageColumn::make('avatar')
                    ->label('Аватар')
                    ->circular()
                    ->alignCenter()
                    ->state(fn ($record) => "https://pashaman.dev/images/authors/{$record->id}.jpg"),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('site')
                    ->label('Сайт')
                    ->searchable(),
                TextColumn::make('nick')
                    ->label('Ник')
                    ->searchable(),
                TextColumn::make('projects_count')
                    ->label('Проекты')
                    ->counts('projects'),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Изменён')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
