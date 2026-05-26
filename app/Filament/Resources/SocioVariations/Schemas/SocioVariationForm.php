<?php

namespace App\Filament\Resources\SocioVariations\Schemas;

use App\Models\SocioVariation;
use App\Models\SocioWorkContract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class SocioVariationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Variazione')
                    ->columns(2)
                    ->schema(self::fields()),
            ]);
    }

    public static function steps(): array
    {
        return [
            Step::make('Socio e variazione')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            self::socioField(),
                            self::tipoField(),
                        ]),
                ]),
            Step::make('Parametri')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema(self::parameterFields()),
                ]),
            Step::make('Verbale')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema(self::verbaleFields()),
                ]),
        ];
    }

    private static function fields(): array
    {
        return [
            self::socioField()->disabled(),
            self::tipoField()->disabled(),
            ...self::parameterFields(disabled: true),
            ...self::verbaleFields(disabled: true),
        ];
    }

    private static function socioField(): Select
    {
        return Select::make('socio_id')
            ->label('Socio')
            ->relationship('socio', 'codice_socio')
            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->codice_socio} - {$record->cognome} {$record->nome}")
            ->searchable(['codice_socio', 'cognome', 'nome', 'codice_fiscale'])
            ->preload()
            ->required();
    }

    private static function tipoField(): Select
    {
        return Select::make('tipo')
            ->label('Tipo variazione')
            ->options(SocioVariation::TIPI)
            ->required()
            ->live();
    }

    private static function parameterFields(bool $disabled = false): array
    {
        return [
            Select::make('tipo_contratto')
                ->label('Tipo contratto')
                ->options(SocioWorkContract::TIPI_CONTRATTO)
                ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto'], true))
                ->required(fn ($get): bool => $get('tipo') === 'variazione_contratto')
                ->live()
                ->disabled($disabled),
            DatePicker::make('data_inizio')
                ->label('Data inizio contratto')
                ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto', 'trasformazione_indeterminato'], true))
                ->disabled($disabled),
            DatePicker::make('data_fine')
                ->label('Data fine contratto')
                ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto'], true))
                ->required(fn ($get): bool => $get('tipo') === 'proroga_contratto' || $get('tipo_contratto') === 'determinato')
                ->disabled($disabled),
            TextInput::make('ore_settimanali')
                ->label('Ore settimanali')
                ->numeric()
                ->step('0.25')
                ->minValue(0)
                ->maxValue(60)
                ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto', 'trasformazione_indeterminato', 'variazione_ore'], true))
                ->required(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'variazione_ore'], true))
                ->disabled($disabled),
            Textarea::make('note')
                ->label('Note')
                ->columnSpanFull()
                ->disabled($disabled),
        ];
    }

    private static function verbaleFields(bool $disabled = false): array
    {
        return [
            DatePicker::make('data_verbale')
                ->label('Data verbale')
                ->required()
                ->default(now())
                ->disabled($disabled),
            DatePicker::make('data_effetto')
                ->label('Data effetto')
                ->required()
                ->default(now())
                ->disabled($disabled),
        ];
    }
}
