<?php

namespace App\Filament\Resources\Comunes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComuneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comune')
                    ->columns(2)
                    ->schema([
                        TextInput::make('progressivo')
                            ->label('Progressivo')
                            ->numeric(),
                        TextInput::make('denominazione')
                            ->label('Denominazione comune')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('ripartizione_geografica')
                            ->label('Ripartizione geografica')
                            ->maxLength(255),
                        TextInput::make('regione')
                            ->label('Regione')
                            ->maxLength(255),
                        TextInput::make('provincia_unita_territoriale')
                            ->label('Provincia/unità territoriale')
                            ->maxLength(255),
                        TextInput::make('codice_catastale')
                            ->label('Codice catastale')
                            ->required()
                            ->length(4)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                    ]),
            ]);
    }
}
