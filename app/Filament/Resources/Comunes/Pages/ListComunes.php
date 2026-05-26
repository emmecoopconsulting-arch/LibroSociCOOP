<?php

namespace App\Filament\Resources\Comunes\Pages;

use App\Filament\Resources\Comunes\ComuneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComunes extends ListRecords
{
    protected static string $resource = ComuneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
