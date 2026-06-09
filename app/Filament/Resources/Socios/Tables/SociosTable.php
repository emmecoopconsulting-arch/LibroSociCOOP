<?php

namespace App\Filament\Resources\Socios\Tables;

use App\Models\AppSetting;
use App\Models\Socio;
use App\Services\LibroSociExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                TextColumn::make('mansione')
                    ->label('Mansione')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => Socio::STATI[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('data_ammissione')
                    ->label('Ammesso il')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('scadenza_permesso_soggiorno')
                    ->label('Scad. permesso')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('mansione')
                    ->label('Mansione')
                    ->options(fn (): array => array_combine(AppSetting::mansioni(), AppSetting::mansioni())),
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
                    BulkAction::make('bulkEdit')
                        ->label('Modifica in blocco')
                        ->icon('heroicon-o-pencil-square')
                        ->modalHeading('Modifica soci selezionati')
                        ->modalSubmitActionLabel('Applica modifiche')
                        ->schema([
                            TextInput::make('quota_sociale')
                                ->label('Quota sociale')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('EUR'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Socio $socio): bool => $socio->update([
                                'quota_sociale' => $data['quota_sociale'],
                            ]));
                        })
                        ->successNotificationTitle('Soci aggiornati')
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
