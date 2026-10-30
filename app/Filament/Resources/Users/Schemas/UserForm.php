<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя'),
                TextInput::make('site')
                    ->label('Сайт'),
                TextInput::make('nick')
                    ->label('Ник'),
                Textarea::make('email')
                    ->label('Email')
                    ->columnSpanFull(),
                Textarea::make('password')
                    ->label('Пароль')
                    ->columnSpanFull(),
            ]);
    }
}
