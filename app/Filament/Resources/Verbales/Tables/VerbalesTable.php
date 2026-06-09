<?php

namespace App\Filament\Resources\Verbales\Tables;

use App\Models\Verbale;
use App\Services\VerbalePdfService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class VerbalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('generato_il', 'desc')
            ->columns([
                TextColumn::make('titolo')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('socio.codice_socio')
                    ->label('Codice socio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('socio.nome_completo')
                    ->label('Socio')
                    ->searchable(['nome', 'cognome']),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => Verbale::TIPI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => Verbale::STATI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('data_verbale')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('generato_il')
                    ->label('Generato il')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(Verbale::TIPI),
                SelectFilter::make('stato')
                    ->label('Stato')
                    ->options(Verbale::STATI),
            ])
            ->recordActions([
                Action::make('genera')
                    ->label('Genera PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (Verbale $record) => app(VerbalePdfService::class)->downloadResponse($record, regenerate: true)),
                Action::make('scarica')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Verbale $record): bool => filled($record->file_path) && Storage::disk('local')->exists($record->file_path))
                    ->action(fn (Verbale $record) => app(VerbalePdfService::class)->downloadResponse($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
