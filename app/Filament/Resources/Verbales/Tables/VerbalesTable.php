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
                    ->visible(fn (Verbale $record): bool => $record->tipo === 'ammissione')
                    ->action(function (Verbale $record) {
                        $verbale = app(VerbalePdfService::class)->generateAdmission($record->socio);

                        return response()->download(Storage::disk('local')->path($verbale->file_path));
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
