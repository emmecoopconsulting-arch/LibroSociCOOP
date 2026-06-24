<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Models\Socio;
use App\Models\WorkAbsence;
use App\Models\WorkOrder;
use App\Models\WorkSite;
use App\Models\WorkVehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ordine di servizio')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('data_servizio')
                            ->label('Data servizio')
                            ->required()
                            ->default(now())
                            ->live(),
                        Select::make('stato')
                            ->label('Stato')
                            ->options(WorkOrder::STATI)
                            ->required()
                            ->default('bozza'),
                        TextInput::make('titolo')
                            ->label('Titolo')
                            ->required()
                            ->default(fn (): string => 'Ordine di servizio del '.now()->format('d/m/Y'))
                            ->maxLength(255),
                        TextInput::make('pdf_path')
                            ->label('PDF archiviato')
                            ->disabled()
                            ->dehydrated(),
                        Textarea::make('note')
                            ->label('Note generali')
                            ->columnSpanFull(),
                    ]),
                Section::make('Cantieri di lavoro')
                    ->schema([
                        Repeater::make('sites')
                            ->label('Cantieri')
                            ->hiddenLabel()
                            ->relationship('sites')
                            ->reorderable(false)
                            ->addActionLabel('Aggiungi cantiere')
                            ->columns(2)
                            ->schema([
                                Select::make('work_site_id')
                                    ->label('Cantiere')
                                    ->options(fn (): array => WorkSite::query()
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (WorkSite $site): array => [
                                            $site->id => "{$site->nome} - {$site->luogo}",
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('orario_inizio')
                                    ->label('Ora inizio')
                                    ->type('time'),
                                TextInput::make('orario_fine')
                                    ->label('Ora fine')
                                    ->type('time'),
                                Select::make('work_vehicle_id')
                                    ->label('Mezzo utilizzato')
                                    ->options(fn (): array => WorkVehicle::query()
                                        ->attivi()
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (WorkVehicle $vehicle): array => [
                                            $vehicle->id => $vehicle->descrizione.' - '.(WorkVehicle::TIPI[$vehicle->tipo] ?? $vehicle->tipo),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload(),
                                Select::make('socio_ids')
                                    ->label('Persone assegnate')
                                    ->options(fn (): array => Socio::query()
                                        ->attivi()
                                        ->where('tipologia', 'ordinario')
                                        ->orderBy('cognome')
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (Socio $socio): array => [
                                            $socio->id => "{$socio->cognome} {$socio->nome}",
                                        ])
                                        ->all())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                                Textarea::make('note')
                                    ->label('Note cantiere')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Assenze')
                    ->schema([
                        Repeater::make('absences')
                            ->label('Assenze')
                            ->hiddenLabel()
                            ->relationship('absences')
                            ->addActionLabel('Aggiungi assenza')
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                Select::make('socio_ids')
                                    ->label('Soci assenti')
                                    ->options(fn (): array => Socio::query()
                                        ->attivi()
                                        ->where('tipologia', 'ordinario')
                                        ->orderBy('cognome')
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (Socio $socio): array => [
                                            $socio->id => "{$socio->codice_socio} - {$socio->cognome} {$socio->nome}",
                                        ])
                                        ->all())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('tipo')
                                    ->label('Tipo assenza')
                                    ->options(WorkAbsence::TIPI)
                                    ->required(),
                                DatePicker::make('data_inizio')
                                    ->label('Data inizio')
                                    ->required(),
                                DatePicker::make('data_fine')
                                    ->label('Data fine')
                                    ->required()
                                    ->afterOrEqual('data_inizio'),
                                Textarea::make('note')
                                    ->label('Note assenza')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
