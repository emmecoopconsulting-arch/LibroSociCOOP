<?php

namespace App\Filament\Resources\SocioMedicalVisitBatches\Tables;

use App\Models\SocioMedicalVisitBatch;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocioMedicalVisitBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_visita', 'desc')
            ->columns([
                TextColumn::make('data_visita')
                    ->label('Data visite')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('visits_count')
                    ->label('Soci')
                    ->counts('visits')
                    ->sortable(),
                TextColumn::make('pdf_count')
                    ->label('PDF')
                    ->state(fn (SocioMedicalVisitBatch $record): int => $record->visits()->whereNotNull('pdf_path')->count()),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registrata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Dettaglio'),
            ]);
    }
}
