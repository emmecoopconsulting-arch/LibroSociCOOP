<?php

namespace App\Filament\Resources\WorkReports\Schemas;

use App\Models\WorkOrder;
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
                            ->placeholder('Generato automaticamente')
                            ->disabled()
                            ->dehydrated()
                            ->unique(ignoreRecord: true),
                        DatePicker::make('data_intervento')
                            ->label('Data intervento')
                            ->required()
                            ->default(now()),
                    ]),
                Section::make('Intervento e cantiere')
                    ->columns(2)
                    ->schema([
                        Select::make('work_order_id')
                            ->label('Ordine di servizio')
                            ->options(fn (): array => WorkOrder::query()
                                ->orderByDesc('data_servizio')
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn (WorkOrder $order): array => [
                                    $order->id => $order->data_servizio?->format('d/m/Y').' - '.$order->titolo,
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
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
                            ->preload(),
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
