<?php

namespace App\Filament\Resources\Socios\RelationManagers;

use App\Filament\Resources\SocioMedicalVisitBatches\RelationManagers\VisitsRelationManager;
use Filament\Tables\Table;

class MedicalVisitsRelationManager extends VisitsRelationManager
{
    protected static string $relationship = 'medicalVisits';

    protected static ?string $title = 'Storico visite mediche';

    public function table(Table $table): Table
    {
        return parent::table($table)->defaultSort('data_visita', 'desc');
    }
}
