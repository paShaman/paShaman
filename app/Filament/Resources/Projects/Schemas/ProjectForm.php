<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('site'),
                TextInput::make('link')
                    ->required(),
                Textarea::make('info'),
                TextInput::make('tags')
                    ->required(),
                TextInput::make('date')
                    ->required(),

                Repeater::make('authors')
                    ->label('Авторы')
                    ->schema([
                        Select::make('user_id')
                            ->label('Автор')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () {
                                return \App\Models\User::all()->mapWithKeys(function ($user) {
                                    $label = view('filament.components.select-option-with-avatar', [
                                        'avatarUrl' => "https://pashaman.dev/images/authors/{$user->id}.jpg",
                                        'name' => $user->name,
                                    ])->render();
                                    return [$user->id => $label];
                                });
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\User::where('name', 'like', "%{$search}%")
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        $label = view('filament.components.select-option-with-avatar', [
                                            'avatarUrl' => "https://pashaman.dev/images/authors/{$user->id}.jpg",
                                            'name' => $user->name,
                                        ])->render();
                                        return [$user->id => $label];
                                    });
                            })
                            ->allowHtml(),
                        TextInput::make('role')
                            ->label('Роль')
                            ->default(''),
                    ])
                    ->addActionLabel('Добавить автора'),

                ToggleButtons::make('active')
                    ->label('Статус')
                    ->required()
                    ->options([
                        1 => 'Активен',
                        0 => 'Неактивен',
                        2 => 'Скрыт',
                    ])
                    ->icons([
                        1 => 'heroicon-o-check-circle',
                        0 => 'heroicon-o-x-circle',
                        2 => 'heroicon-o-eye-slash',
                    ])
                    ->colors([
                        1 => 'success',
                        0 => 'danger',
                        2 => 'warning',
                    ])
                    ->default(1)
                    ->inline(),
            ]);
    }
}
