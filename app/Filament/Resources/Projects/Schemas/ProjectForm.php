<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                TextInput::make('site')
                    ->required(),
                TextInput::make('link')
                    ->required(),
                Textarea::make('info')
                    ->required(),
                TextInput::make('tags')
                    ->required(),
                TextInput::make('date')
                    ->required(),

                Repeater::make('authors')
                    ->relationship()
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
                            ->label('Роль'),
                    ])
                    ->addActionLabel('Добавить автора'),

                Select::make('active')
                    ->required()
                    ->options([
                        0 => 'Неактивен',
                        1 => 'Активен',
                        2 => 'Скрыт',
                    ])
                    ->default(1),
            ]);
    }
}
