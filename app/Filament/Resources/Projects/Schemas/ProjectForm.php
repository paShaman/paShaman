<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required(),
                TextInput::make('site')
                    ->label('Сайт'),
                TextInput::make('link')
                    ->label('Ссылка')
                    ->required(),
                Textarea::make('info')
                    ->label('Описание'),
                TextInput::make('tags')
                    ->label('Теги')
                    ->required(),
                TextInput::make('date')
                    ->label('Дата')
                    ->required(),

                FileUpload::make('images')
                    ->label('Изображения проекта')
                    ->helperText('Файлы сохраняются в /images/projects/{link}')
                    ->disk('projects')
                    ->directory(fn (Get $get): string => trim((string) $get('link'), '/'))
                    ->multiple()
                    ->image()
                    ->preserveFilenames()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('200')
                    ->openable()
                    ->downloadable()
                    ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('projects')->delete($file))
                    ->visibleOn('edit'),

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
