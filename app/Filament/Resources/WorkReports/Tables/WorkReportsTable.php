<?php

namespace App\Filament\Resources\WorkReports\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

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
                    ->state(fn ($record): string => $record->operatorHoursBySocio()
                        ->map(fn (array $row): string => "{$row['socio']->cognome} {$row['socio']->nome} ({$row['hours']} ore)")
                        ->join(', '))
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('total_hours')
                    ->label('Totale ore')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('oggetto')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(60),
            ])
            ->recordActions([
                Action::make('scaricaRapportino')
                    ->label('Scarica rapportino')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record): string => route('local-files.file', [
                        'path' => Crypt::encryptString($record->rapportino_path),
                        'download' => true,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => filled($record->rapportino_path) && Storage::disk('local')->exists($record->rapportino_path)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
