<?php

namespace App\Filament\Resources\SocioMedicalVisitBatches\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SocioMedicalVisitBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registrazione visite mediche')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('data_visita')
                            ->label('Data visita')
                            ->required(),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
