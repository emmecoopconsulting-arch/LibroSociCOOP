<?php

namespace App\Filament\Resources\WorkAbsences\Tables;

use App\Models\Socio;
use App\Models\WorkAbsence;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkAbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.data_servizio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('socio_ids')
                    ->label('Soci')
                    ->formatStateUsing(fn (?array $state): string => Socio::query()
                        ->whereIn('id', $state ?? [])
                        ->orderBy('cognome')
                        ->orderBy('nome')
                        ->get()
                        ->pluck('nome_completo')
                        ->join(', '))
                    ->wrap(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => WorkAbsence::TIPI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('data_inizio')
                    ->label('Dal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_fine')
                    ->label('Al')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(WorkAbsence::TIPI),
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
