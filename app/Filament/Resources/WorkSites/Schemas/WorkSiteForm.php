<?php

namespace App\Filament\Resources\WorkSites\Schemas;

use App\Models\Socio;
use App\Models\WorkOrder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cantiere di lavoro')
                    ->columns(2)
                    ->schema([
                        Select::make('work_order_id')
                            ->label('Ordine di servizio')
                            ->relationship('order', 'titolo')
                            ->getOptionLabelFromRecordUsing(fn (WorkOrder $record): string => $record->data_servizio?->format('d/m/Y').' - '.$record->titolo)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('work_vehicle_id')
                            ->label('Mezzo utilizzato')
                            ->relationship('vehicle', 'nome')
                            ->searchable()
                            ->preload(),
                        TextInput::make('nome')
                            ->label('Cantiere')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('luogo')
                            ->label('Luogo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('orario_inizio')
                            ->label('Ora inizio')
                            ->type('time')
                            ->required(),
                        TextInput::make('orario_fine')
                            ->label('Ora fine')
                            ->type('time')
                            ->required()
                            ->after('orario_inizio'),
                        Repeater::make('assignments')
                            ->label('Persone assegnate')
                            ->relationship('assignments')
                            ->addActionLabel('Aggiungi persona')
                            ->reorderable(false)
                            ->schema([
                                Select::make('socio_id')
                                    ->label('Socio lavoratore attivo')
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
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
