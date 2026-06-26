<?php

namespace App\Filament\Resources\WorkReports\Schemas;

use App\Models\Socio;
use App\Models\WorkSite;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Protocollo interno')
                    ->columns(2)
                    ->schema([
                        TextInput::make('protocollo')
                            ->label('Protocollo')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),
                        DatePicker::make('data_intervento')
                            ->label('Data intervento')
                            ->required()
                            ->default(now()),
                    ]),
                Section::make('Intervento e cantiere')
                    ->columns(2)
                    ->schema([
                        TextInput::make('work_site_name')
                            ->label('Cliente / cantiere')
                            ->datalist(fn (): array => WorkSite::labels())
                            ->placeholder('Scrivi o scegli cliente / cantiere')
                            ->required(),
                        Select::make('socio_ids')
                            ->label('Operatori che hanno svolto il servizio')
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
                            ->required(),
                        TextInput::make('oggetto')
                            ->label('Oggetto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('descrizione_lavoro')
                            ->label('Rapporto del lavoro svolto')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
                Section::make('Scansione rapportino')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('rapportino_path')
                            ->label('Allegato scansione')
                            ->disk('local')
                            ->directory('rapporti-interventi')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(15360)
                            ->downloadable()
                            ->openable()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('note')
                            ->label('Note interne')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
