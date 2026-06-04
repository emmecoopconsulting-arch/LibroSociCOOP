<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
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
