<?php

namespace App\Filament\Resources\Verbales\Pages;

use App\Filament\Resources\Verbales\VerbaleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVerbale extends EditRecord
{
    protected static string $resource = VerbaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
