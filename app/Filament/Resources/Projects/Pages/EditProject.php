<?php

namespace App\Filament\Resources\Projects\Pages;

use AllowDynamicProperties;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

#[AllowDynamicProperties]
class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->icon(Heroicon::OutlinedCheck);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->icon(Heroicon::OutlinedXMark);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Загружаем существующих авторов с ролями для отображения в Repeater
        $data['authors'] = $this->record->authors->map(function ($author) {
            return [
                'user_id' => $author->id,
                'role'    => $author->pivot->role ?? '',
            ];
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Извлекаем авторов из данных перед сохранением,
        // чтобы сохранить их вручную с pivot-полем role
        if (array_key_exists('authors', $data)) {
            $this->authorsData = $data['authors'];
            unset($data['authors']);
        } else {
            $this->authorsData = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->authorsData === null) {
            return;
        }

        $authors = [];
        foreach ($this->authorsData as $author) {
            if (!empty($author['user_id'])) {
                $authors[$author['user_id']] = ['role' => $author['role'] ?? ''];
            }
        }

        $this->record->authors()->sync($authors);
    }
}
