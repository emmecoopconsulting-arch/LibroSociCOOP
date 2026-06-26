<?php

namespace App\Filament\Resources\WorkReports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_intervento', 'desc')
            ->columns([
                TextColumn::make('protocollo')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_intervento')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('work_site_name')
                    ->label('Cliente / cantiere')
                    ->state(fn ($record): string => $record->displaySiteName())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('socio_ids')
                    ->label('Operatori')
                    ->state(fn ($record): string => $record->assignedSocios()
                        ->map(fn ($socio): string => "{$socio->cognome} {$socio->nome}")
                        ->join(', '))
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('oggetto')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(60),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
