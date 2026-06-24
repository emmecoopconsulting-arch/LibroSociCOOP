<?php

namespace App\Filament\Resources\WorkAbsences\Schemas;

use App\Models\Socio;
use App\Models\WorkAbsence;
use App\Models\WorkOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkAbsenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assenza')
                    ->columns(2)
                    ->schema([
                        Select::make('work_order_id')
                            ->label('Ordine di servizio')
                            ->relationship('order', 'titolo')
                            ->getOptionLabelFromRecordUsing(fn (WorkOrder $record): string => $record->data_servizio?->format('d/m/Y').' - '.$record->titolo)
                            ->searchable()
                            ->preload()
                            ->required(),
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
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
