<?php

namespace App\Filament\Resources\Socios\Schemas;

use App\Models\Socio;
use App\Rules\CodiceFiscale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class SocioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati socio')
                    ->columns(2)
                    ->schema([
                        TextInput::make('codice_socio')
                            ->label('Codice socio')
                            ->placeholder('Generato automaticamente')
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cognome')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('codice_fiscale')
                            ->label('Codice fiscale')
                            ->required()
                            ->length(16)
                            ->helperText('Data e luogo di nascita vengono ricavati automaticamente al salvataggio.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null)
                            ->rules(fn ($record): array => [
                                new CodiceFiscale,
                                Rule::unique('socios', 'codice_fiscale')->ignore($record?->id),
                            ]),
                        DatePicker::make('data_nascita')
                            ->label('Data nascita')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('luogo_nascita')
                            ->label('Luogo nascita')
                            ->disabled()
                            ->dehydrated(),
                    ]),
                Section::make('Rapporto sociale')
                    ->columns(2)
                    ->schema([
                        Select::make('tipologia')
                            ->label('Tipologia socio')
                            ->options(Socio::TIPOLOGIE)
                            ->required(),
                        Select::make('stato')
                            ->label('Stato')
                            ->options(Socio::STATI)
                            ->required()
                            ->default('attivo')
                            ->live(),
                        DatePicker::make('data_ammissione')
                            ->label('Data ammissione')
                            ->required(fn ($get): bool => $get('stato') === 'attivo'),
                        DatePicker::make('data_uscita')
                            ->label('Data uscita'),
                        TextInput::make('quota_sociale')
                            ->label('Quota sociale')
                            ->numeric()
                            ->default(0)
                            ->prefix('EUR'),
                        TextInput::make('capitale_versato')
                            ->label('Capitale versato')
                            ->numeric()
                            ->default(0)
                            ->prefix('EUR'),
                    ]),
                Section::make('Contatti')
                    ->columns(2)
                    ->schema([
                        Select::make('comune_residenza_id')
                            ->label('Comune residenza')
                            ->relationship('comuneResidenza', 'denominazione')
                            ->searchable()
                            ->preload(),
                        TextInput::make('indirizzo')
                            ->label('Indirizzo')
                            ->maxLength(255),
                        TextInput::make('telefono')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
