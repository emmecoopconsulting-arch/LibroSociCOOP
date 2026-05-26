<?php

namespace App\Filament\Resources\Verbales\Schemas;

use App\Models\Verbale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerbaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verbale')
                    ->columns(2)
                    ->schema([
                        Select::make('socio_id')
                            ->label('Socio')
                            ->relationship('socio', 'codice_socio')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->codice_socio} - {$record->cognome} {$record->nome}")
                            ->searchable(['codice_socio', 'cognome', 'nome', 'codice_fiscale'])
                            ->preload()
                            ->required(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(Verbale::TIPI)
                            ->required(),
                        Select::make('stato')
                            ->label('Stato')
                            ->options(Verbale::STATI)
                            ->required()
                            ->default('da_generare'),
                        TextInput::make('titolo')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('data_verbale')
                            ->label('Data verbale')
                            ->required(),
                        TextInput::make('file_path')
                            ->label('Percorso file')
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
