<?php

namespace App\Filament\Resources\WorkVehicles\Pages;

use App\Filament\Resources\WorkVehicles\WorkVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkVehicles extends ListRecords
{
    protected static string $resource = WorkVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
