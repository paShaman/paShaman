<?php

namespace App\Filament\Resources\Money\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('date_payed'),
                TextInput::make('is_payed')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options([
            'plan' => 'Plan',
            'active' => 'Active',
            'cancel' => 'Cancel',
            'finish' => 'Finish',
            'payed' => 'Payed',
        ])
                    ->default('plan')
                    ->required(),
                Textarea::make('comment')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
