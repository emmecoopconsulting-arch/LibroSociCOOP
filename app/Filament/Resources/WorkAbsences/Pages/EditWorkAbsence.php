<?php

namespace App\Filament\Resources\WorkAbsences\Pages;

use App\Filament\Resources\WorkAbsences\WorkAbsenceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkAbsence extends EditRecord
{
    protected static string $resource = WorkAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
