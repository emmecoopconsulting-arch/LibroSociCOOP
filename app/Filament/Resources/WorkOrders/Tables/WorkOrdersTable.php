<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Models\WorkOrder;
use App\Services\WorkOrderPdfService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_servizio', 'desc')
            ->columns([
                TextColumn::make('data_servizio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('titolo')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => WorkOrder::STATI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('sites_count')
                    ->label('Cantieri')
                    ->counts('sites')
                    ->sortable(),
                TextColumn::make('absences_count')
                    ->label('Assenze')
                    ->counts('absences')
                    ->sortable(),
                TextColumn::make('archiviato_il')
                    ->label('Archiviato il')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('stato')
                    ->label('Stato')
                    ->options(WorkOrder::STATI),
            ])
            ->recordActions([
                Action::make('generaPdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (WorkOrder $record) => app(WorkOrderPdfService::class)->downloadResponse($record, regenerate: true)),
                Action::make('archiviaPdf')
                    ->label('Scarica e archivia')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->requiresConfirmation()
                    ->action(fn (WorkOrder $record) => app(WorkOrderPdfService::class)->downloadResponse($record, regenerate: true, archive: true)),
                Action::make('scaricaArchivio')
                    ->label('PDF archiviato')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (WorkOrder $record): bool => filled($record->pdf_path) && Storage::disk('local')->exists($record->pdf_path))
                    ->action(fn (WorkOrder $record) => app(WorkOrderPdfService::class)->downloadResponse($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
