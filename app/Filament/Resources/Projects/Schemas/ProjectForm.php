<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
                Textarea::make('info')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('link')
                    ->required(),
                Toggle::make('active')
                    ->required(),
                TextInput::make('tags')
                    ->required(),
                TextInput::make('date')
                    ->required(),
            ]);
    }
}
