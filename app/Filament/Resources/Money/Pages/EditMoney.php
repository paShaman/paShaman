<?php

namespace App\Filament\Resources\Money\Pages;

use App\Filament\Resources\Money\MoneyResource;
use App\Models\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMoney extends EditRecord
{
    protected static string $resource = MoneyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markPayed')
                ->label('Оплачено')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->action(function (Money $record): void {
                    if ($record->is_payed || $record->status !== 'finish') {
                        Notification::make()
                            ->title('Нельзя отметить оплаченным')
                            ->body('Операция должна иметь статус finish и быть неоплаченной.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->update([
                        'status' => 'payed',
                        'is_payed' => true,
                        'date_payed' => now()->toDateString(),
                    ]);

                    $this->refreshFormData([
                        'status',
                        'is_payed',
                        'date_payed',
                    ]);

                    Notification::make()
                        ->title('Операция отмечена как оплаченная')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
