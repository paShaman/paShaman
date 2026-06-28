<?php

namespace App\Filament\Resources\Money\Pages;

use App\Filament\Resources\Money\MoneyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMoney extends ListRecords
{
    protected static string $resource = MoneyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
