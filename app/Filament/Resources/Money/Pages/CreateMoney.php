<?php

namespace App\Filament\Resources\Money\Pages;

use App\Filament\Resources\Money\MoneyResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateMoney extends CreateRecord
{
    protected static string $resource = MoneyResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->icon(Heroicon::OutlinedCheckCircle);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->icon(Heroicon::OutlinedXMark);
    }
}
