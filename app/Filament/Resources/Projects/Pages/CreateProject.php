<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Извлекаем авторов из данных перед созданием,
        // чтобы сохранить их вручную с pivot-полем role
        $this->authorsData = $data['authors'] ?? [];
        unset($data['authors']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $authors = [];
        foreach ($this->authorsData as $author) {
            if (!empty($author['user_id'])) {
                $authors[$author['user_id']] = ['role' => $author['role'] ?? ''];
            }
        }

        $this->record->authors()->sync($authors);
    }
}
