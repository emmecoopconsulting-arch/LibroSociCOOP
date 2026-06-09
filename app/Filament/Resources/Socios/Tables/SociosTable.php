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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                            CheckboxList::make('fields_to_update')
                                ->label('Campi da modificare')
                                ->options([
                                    'tipologia' => 'Tipologia socio',
                                    'stato' => 'Stato',
                                    'data_ammissione' => 'Data ammissione',
                                    'data_uscita' => 'Data uscita',
                                    'ha_permesso_soggiorno' => 'Permesso di soggiorno',
                                    'scadenza_permesso_soggiorno' => 'Scadenza permesso di soggiorno',
                                    'mansione' => 'Mansione',
                                    'quota_sociale' => 'Quota sociale',
                                    'capitale_versato' => 'Capitale versato',
                                    'note' => 'Note',
                                ])
                                ->columns(2)
                                ->minItems(1)
                                ->required()
                                ->live(),
                            Section::make('Valori da applicare')
                                ->columns(2)
                                ->schema([
                                    Select::make('tipologia')
                                        ->label('Tipologia socio')
                                        ->options(Socio::TIPOLOGIE)
                                        ->required(fn ($get): bool => self::bulkFieldSelected($get, 'tipologia'))
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'tipologia')),
                                    Select::make('stato')
                                        ->label('Stato')
                                        ->options(Socio::STATI)
                                        ->required(fn ($get): bool => self::bulkFieldSelected($get, 'stato'))
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'stato')),
                                    DatePicker::make('data_ammissione')
                                        ->label('Data ammissione')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'data_ammissione')),
                                    DatePicker::make('data_uscita')
                                        ->label('Data uscita')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'data_uscita')),
                                    Toggle::make('ha_permesso_soggiorno')
                                        ->label('Permesso di soggiorno')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'ha_permesso_soggiorno')),
                                    DatePicker::make('scadenza_permesso_soggiorno')
                                        ->label('Scadenza permesso di soggiorno')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'scadenza_permesso_soggiorno')),
                                    Select::make('mansione')
                                        ->label('Mansione')
                                        ->options(fn (): array => array_combine(AppSetting::mansioni(), AppSetting::mansioni()))
                                        ->searchable()
                                        ->preload()
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'mansione')),
                                    TextInput::make('quota_sociale')
                                        ->label('Quota sociale')
                                        ->numeric()
                                        ->required(fn ($get): bool => self::bulkFieldSelected($get, 'quota_sociale'))
                                        ->minValue(0)
                                        ->prefix('EUR')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'quota_sociale')),
                                    TextInput::make('capitale_versato')
                                        ->label('Capitale versato')
                                        ->numeric()
                                        ->required(fn ($get): bool => self::bulkFieldSelected($get, 'capitale_versato'))
                                        ->minValue(0)
                                        ->prefix('EUR')
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'capitale_versato')),
                                    Textarea::make('note')
                                        ->label('Note')
                                        ->columnSpanFull()
                                        ->visible(fn ($get): bool => self::bulkFieldSelected($get, 'note')),
                                ]),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            $fields = $data['fields_to_update'] ?? [];
                            $updates = [];

                            foreach ($fields as $field) {
                                $updates[$field] = $data[$field] ?? null;
                            }

                            $records->each(fn (Socio $socio): bool => $socio->update($updates));
                        })
                        ->successNotificationTitle('Soci aggiornati')
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function bulkFieldSelected(callable $get, string $field): bool
    {
        return in_array($field, $get('fields_to_update') ?? [], true);
    }
}
