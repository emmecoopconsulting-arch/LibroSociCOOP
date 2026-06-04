<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => auth()->id() === $this->record->getKey()),
        ];
    }

    protected function afterSave(): void
    {
        activity('sicurezza')
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->event('roles_updated')
            ->withProperties([
                'roles' => $this->record->roles()->pluck('name')->all(),
            ])
            ->log('ruoli utente aggiornati');
    }
}
