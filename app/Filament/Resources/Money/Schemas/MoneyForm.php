<?php

namespace App\Filament\Resources\Money\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MoneyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->label('Год')
                    ->required()
                    ->numeric(),
                TextInput::make('month')
                    ->label('Месяц')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->label('Тип')
                    ->options([
                        'site' => 'Сайт',
                        'salary' => 'Зарплата',
                        'bank' => 'Банк',
                        'other' => 'Прочее',
                        'delta' => 'Разница',
                    ])
                    ->default('site')
                    ->required(),
                TextInput::make('sum')
                    ->label('Сумма')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('project')
                    ->label('Проект')
                    ->columnSpanFull(),
                DatePicker::make('date_payed')
                    ->label('Дата оплаты'),
                Toggle::make('is_payed')
                    ->label('Оплачено')
                    ->default(false),
                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'plan' => 'План',
                        'active' => 'Активная',
                        'cancel' => 'Отменена',
                        'finish' => 'Завершена',
                        'payed' => 'Оплачена',
                    ])
                    ->default('plan')
                    ->native(false)
                    ->required(),
                Textarea::make('comment')
                    ->label('Комментарий')
                    ->columnSpanFull(),
            ]);
    }
}
