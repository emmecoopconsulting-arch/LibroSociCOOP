<?php

namespace App\Filament\Resources\Comunes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComunesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('progressivo')
                    ->label('Progressivo')
                    ->sortable(),
                TextColumn::make('denominazione')
                    ->label('Comune')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codice_catastale')
                    ->label('Codice catastale')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provincia_unita_territoriale')
                    ->label('Provincia')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('regione')
                    ->label('Regione')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
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
