<?php

namespace App\Filament\Resources\Money\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MoneyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->required()
                    ->numeric(),
                TextInput::make('month')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->options([
            'site' => 'Site',
            'salary' => 'Salary',
            'bank' => 'Bank',
            'other' => 'Other',
            'delta' => 'Delta',
        ])
                    ->default('site')
                    ->required(),
                TextInput::make('sum')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('project')
                    ->columnSpanFull(),
                DatePicker::make('date_payed'),
                Toggle::make('is_payed')
                    ->default(false),
                Select::make('status')
                    ->options([
            'plan' => 'Plan',
            'active' => 'Active',
            'cancel' => 'Cancel',
            'finish' => 'Finish',
            'payed' => 'Payed',
        ])
                    ->default('plan')
                    ->native(false)
                    ->required(),
                Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }
}
