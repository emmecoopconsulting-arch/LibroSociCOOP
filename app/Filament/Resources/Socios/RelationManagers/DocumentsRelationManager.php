<?php

namespace App\Filament\Resources\Socios\RelationManagers;

use App\Models\SocioDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento socio')
                    ->columns(2)
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo documento')
                            ->options(SocioDocument::TIPI)
                            ->required()
                            ->native(false),
                        TextInput::make('numero_documento')
                            ->label('Numero documento')
                            ->maxLength(255),
                        DatePicker::make('data_rilascio')
                            ->label('Data rilascio'),
                        DatePicker::make('data_scadenza')
                            ->label('Data scadenza'),
                        FileUpload::make('file_path')
                            ->label('File documento')
                            ->disk('local')
                            ->directory('documenti-soci')
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
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_scadenza')
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => SocioDocument::TIPI[$state] ?? (string) $state)
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('numero_documento')
                    ->label('Numero')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('periodo_riferimento')
                    ->label('Periodo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('data_rilascio')
                    ->label('Rilascio')
                    ->date('d/m/Y')
                    ->toggleable(),
                TextColumn::make('data_scadenza')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Caricato il')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('scarica')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record): string => route('local-files.file', [
                        'path' => Crypt::encryptString($record->file_path),
                        'download' => true,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => filled($record->file_path) && Storage::disk('local')->exists($record->file_path)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
