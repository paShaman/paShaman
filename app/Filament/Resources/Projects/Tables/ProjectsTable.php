<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Project; // Импортируем модель проекта
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('authors_avatars')
                    ->label('Авторы')
                    ->stacked()
                    ->circular()
                    ->alignCenter()
                    ->state(function (Project $record) {
                        return $record->authors->map(function ($author) {
                            return $author->avatar_url;
                        })->toArray();
                    })
                    ->tooltip(function (Project $record) {
                        return $record->authors->map(function ($author) {
                            $role = $author->pivot->role ? " ({$author->pivot->role})" : '';
                            return "{$author->name}{$role}";
                        })->join("\n");
                    }),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('link')
                    ->searchable(),
                TextColumn::make('active')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '1' => 'success',
                        '2' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '1' => 'Активен',
                        '2' => 'Скрыт',
                        default => 'Неактивен',
                    })
                    ->sortable(),
                TextColumn::make('tags')
                    ->searchable(),
                TextColumn::make('date')
                    ->searchable(),
                TextColumn::make('site')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn ($record) => $record->status === 'finish' ? 'table-row-accent bg-warning-50' : null)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('authors'))
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