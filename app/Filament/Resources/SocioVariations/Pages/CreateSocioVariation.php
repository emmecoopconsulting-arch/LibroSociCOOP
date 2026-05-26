<?php

namespace App\Filament\Resources\SocioVariations\Pages;

use App\Filament\Resources\SocioVariations\Schemas\SocioVariationForm;
use App\Filament\Resources\SocioVariations\SocioVariationResource;
use App\Services\SocioVariationService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;

class CreateSocioVariation extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SocioVariationResource::class;

    public function getSteps(): array
    {
        return SocioVariationForm::steps();
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(SocioVariationService::class)->createAndApply($data);
    }
}
