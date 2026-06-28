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
                TextInput::make('name'),
                TextInput::make('site'),
                TextInput::make('nick'),
                Textarea::make('email')
                    ->label('Email address')
                    ->columnSpanFull(),
                Textarea::make('password')
                    ->columnSpanFull(),
            ]);
    }
}
