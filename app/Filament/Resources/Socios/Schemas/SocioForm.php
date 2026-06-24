<?php

namespace App\Filament\Resources\Socios\Schemas;

use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\SocioWorkContract;
use App\Rules\CodiceFiscale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                            ->required()
                            ->default('ordinario')
                            ->live(),
                        Select::make('stato')
                            ->label('Stato')
                            ->options(Socio::STATI)
                            ->required()
                            ->default('attivo')
                            ->live(),
                        Toggle::make('is_cda_member')
                            ->label('Membro CDA')
                            ->default(false),
                        DatePicker::make('data_ammissione')
                            ->label('Data ammissione')
                            ->required(fn ($get): bool => $get('stato') === 'attivo'),
                        FileUpload::make('verbale_cda_path')
                            ->label('Verbale CDA')
                            ->disk('local')
                            ->directory('verbali-cda')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->helperText('Caricare il verbale del CDA in PDF, se disponibile.'),
                        DatePicker::make('data_uscita')
                            ->label('Data uscita'),
                        Toggle::make('ha_permesso_soggiorno')
                            ->label('Permesso di soggiorno')
                            ->live(),
                        DatePicker::make('scadenza_permesso_soggiorno')
                            ->label('Scadenza permesso di soggiorno')
                            ->visible(fn ($get): bool => (bool) $get('ha_permesso_soggiorno'))
                            ->required(fn ($get): bool => (bool) $get('ha_permesso_soggiorno')),
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
                Section::make('Contratto di lavoro')
                    ->columns(2)
                    ->visible(fn ($get): bool => $get('tipologia') === 'ordinario')
                    ->schema([
                        Select::make('contract_tipo_contratto')
                            ->label('Tipo contratto')
                            ->options(SocioWorkContract::TIPI_CONTRATTO)
                            ->required(fn ($get): bool => filled($get('contract_data_inizio')) || filled($get('contract_data_fine')) || filled($get('contract_ore_settimanali')))
                            ->live(),
                        DatePicker::make('contract_data_inizio')
                            ->label('Data inizio contratto')
                            ->required(fn ($get): bool => filled($get('contract_tipo_contratto')) || filled($get('contract_data_fine')) || filled($get('contract_ore_settimanali'))),
                        DatePicker::make('contract_data_fine')
                            ->label('Data fine contratto')
                            ->visible(fn ($get): bool => $get('contract_tipo_contratto') === 'determinato')
                            ->required(fn ($get): bool => $get('contract_tipo_contratto') === 'determinato'),
                        TextInput::make('contract_ore_settimanali')
                            ->label('Ore settimanali')
                            ->numeric()
                            ->step('0.25')
                            ->minValue(0)
                            ->maxValue(60),
                        Select::make('mansione')
                            ->label('Mansione')
                            ->options(fn (): array => array_combine(AppSetting::mansioni(), AppSetting::mansioni()))
                            ->searchable()
                            ->preload(),
                        Textarea::make('contract_note')
                            ->label('Note contratto')
                            ->columnSpanFull(),
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
