<?php

namespace App\Filament\Resources\WorkAbsences\Pages;

use App\Filament\Resources\WorkAbsences\WorkAbsenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkAbsences extends ListRecords
{
    protected static string $resource = WorkAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
