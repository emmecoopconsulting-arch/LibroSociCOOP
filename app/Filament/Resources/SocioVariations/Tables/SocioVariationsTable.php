<?php

namespace App\Filament\Resources\SocioVariations\Tables;

use App\Models\SocioVariation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SocioVariationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('socio.codice_socio')
                    ->label('Codice socio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('socio.nome_completo')
                    ->label('Socio')
                    ->searchable(['nome', 'cognome']),
                TextColumn::make('tipo')
                    ->label('Variazione')
                    ->formatStateUsing(fn (?string $state): string => SocioVariation::TIPI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('data_effetto')
                    ->label('Decorrenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('verbale.titolo')
                    ->label('Verbale')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => SocioVariation::STATI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo variazione')
                    ->options(SocioVariation::TIPI),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_effetto', 'desc');
    }
}
