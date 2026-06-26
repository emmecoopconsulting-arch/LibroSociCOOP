<?php

namespace App\Filament\Resources\SocioMedicalVisitBatches\Pages;

use App\Filament\Resources\SocioMedicalVisitBatches\SocioMedicalVisitBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocioMedicalVisitBatch extends EditRecord
{
    protected static string $resource = SocioMedicalVisitBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
