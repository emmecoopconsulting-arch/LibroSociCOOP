<?php

namespace App\Filament\Resources\WorkVehicles\Pages;

use App\Filament\Resources\WorkVehicles\WorkVehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkVehicle extends EditRecord
{
    protected static string $resource = WorkVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
