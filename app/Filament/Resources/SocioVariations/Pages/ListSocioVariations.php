<?php

namespace App\Filament\Resources\SocioVariations\Pages;

use App\Filament\Resources\SocioVariations\SocioVariationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocioVariations extends ListRecords
{
    protected static string $resource = SocioVariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuova variazione guidata'),
        ];
    }
}
