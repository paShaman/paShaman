<?php

namespace App\Filament\Resources\Money\Pages;

use App\Filament\Resources\Money\MoneyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMoney extends EditRecord
{
    protected static string $resource = MoneyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
