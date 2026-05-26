<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\Socio;
use App\Services\LibroSociExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SociosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codice_socio')
                    ->label('Codice socio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome_completo')
                    ->label('Socio')
                    ->searchable(['nome', 'cognome']),
                TextColumn::make('codice_fiscale')
                    ->label('Codice fiscale')
                    ->searchable(),
                TextColumn::make('tipologia')
                    ->label('Tipologia')
                    ->formatStateUsing(fn (?string $state): string => Socio::TIPOLOGIE[$state] ?? (string) $state)
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => Socio::STATI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('data_ammissione')
                    ->label('Ammesso il')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('capitale_versato')
                    ->label('Capitale')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipologia')
                    ->label('Tipologia')
                    ->options(Socio::TIPOLOGIE),
                SelectFilter::make('stato')
                    ->label('Stato')
                    ->options(Socio::STATI),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('libroPdf')
                    ->label('Esporta libro soci PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => app(LibroSociExportService::class)->pdfResponse()),
                Action::make('libroExcel')
                    ->label('Esporta libro soci Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => app(LibroSociExportService::class)->excelResponse()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
