<?php

namespace App\Filament\Resources\Comunes\Pages;

use App\Filament\Resources\Comunes\ComuneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComune extends EditRecord
{
    protected static string $resource = ComuneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
