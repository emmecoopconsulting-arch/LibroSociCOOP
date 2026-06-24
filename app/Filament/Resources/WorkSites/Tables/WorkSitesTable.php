<?php

namespace App\Filament\Resources\WorkSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orario_inizio')
            ->columns([
                TextColumn::make('order.data_servizio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('nome')
                    ->label('Cantiere')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('luogo')
                    ->label('Luogo')
                    ->searchable(),
                TextColumn::make('orario_inizio')
                    ->label('Inizio'),
                TextColumn::make('orario_fine')
                    ->label('Fine'),
                TextColumn::make('vehicle.nome')
                    ->label('Mezzo')
                    ->placeholder('-'),
                TextColumn::make('assignments_count')
                    ->label('Persone')
                    ->counts('assignments'),
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
