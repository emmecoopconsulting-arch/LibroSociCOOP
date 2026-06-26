<?php

namespace App\Filament\Resources\WorkReports\Schemas;

use App\Models\Socio;
use App\Models\WorkSite;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;

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
                        Repeater::make('operator_hours')
                            ->label('Operatori che hanno svolto il servizio')
                            ->addActionLabel('Aggiungi operatore')
                            ->reorderable(false)
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::refreshTotalHours($get, $set))
                            ->schema([
                                Select::make('socio_id')
                                    ->label('Operatore')
                                    ->options(fn (): array => self::operatorOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::refreshTotalHours($get, $set)),
                                TextInput::make('hours')
                                    ->label('Ore')
                                    ->numeric()
                                    ->step('0.25')
                                    ->minValue(0)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::refreshTotalHours($get, $set)),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('total_hours')
                            ->label('Totale ore')
                            ->numeric()
                            ->step('0.25')
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->suffix('ore'),
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
                            ->getDownloadableFileUrlUsing(fn (string $file): string => route('local-files.file', [
                                'path' => Crypt::encryptString($file),
                                'download' => true,
                            ]))
                            ->openable()
                            ->getOpenableFileUrlUsing(fn (string $file): string => route('local-files.file', [
                                'path' => Crypt::encryptString($file),
                            ]))
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('note')
                            ->label('Note interne')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function operatorOptions(): array
    {
        return Socio::query()
            ->attivi()
            ->where('tipologia', 'ordinario')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Socio $socio): array => [
                $socio->id => "{$socio->cognome} {$socio->nome}",
            ])
            ->all();
    }

    private static function refreshTotalHours(Get $get, Set $set): float
    {
        $rows = $get('/data.operator_hours') ?? $get('../../operator_hours') ?? $get('operator_hours') ?? [];

        $total = collect($rows)
            ->sum(fn (array $row): float => filled($row['hours'] ?? null) ? (float) $row['hours'] : 0.0);

        $set('/data.total_hours', round($total, 2));

        return $total;
    }
}
