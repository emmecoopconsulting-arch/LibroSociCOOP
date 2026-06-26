<?php

namespace App\Filament\Resources\SocioMedicalVisitBatches\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Dettaglio soci e PDF';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Visita medica socio')
                    ->columns(2)
                    ->schema([
                        Select::make('socio_id')
                            ->label('Socio')
                            ->relationship('socio', 'codice_socio')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->codice_socio} - {$record->cognome} {$record->nome}")
                            ->disabled()
                            ->dehydrated(),
                        DatePicker::make('data_visita')
                            ->label('Data visita')
                            ->required(),
                        DatePicker::make('scadenza')
                            ->label('Scadenza')
                            ->required(),
                        FileUpload::make('pdf_path')
                            ->label('PDF visita medica')
                            ->disk('local')
                            ->directory('visite-mediche')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(15360)
                            ->downloadable()
                            ->getDownloadableFileUrlUsing(fn (string $file): string => route('visite-mediche.file', [
                                'path' => Crypt::encryptString($file),
                                'download' => true,
                            ]))
                            ->openable()
                            ->getOpenableFileUrlUsing(fn (string $file): string => route('visite-mediche.file', [
                                'path' => Crypt::encryptString($file),
                            ]))
                            ->columnSpanFull(),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('socio.codice_socio')
                    ->label('Codice socio')
                    ->searchable(),
                TextColumn::make('socio.nome_completo')
                    ->label('Socio')
                    ->searchable(),
                TextColumn::make('data_visita')
                    ->label('Data visita')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('scadenza')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('pdf_path')
                    ->label('PDF')
                    ->boolean()
                    ->state(fn ($record): bool => filled($record->pdf_path)),
            ])
            ->recordActions([
                Action::make('downloadPdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn ($record): string => route('visite-mediche.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => filled($record->pdf_path) && Storage::disk('local')->exists($record->pdf_path)),
                EditAction::make(),
            ]);
    }
}
