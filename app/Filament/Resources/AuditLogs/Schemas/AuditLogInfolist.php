<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Data')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('log_name')
                            ->label('Area')
                            ->badge(),
                        TextEntry::make('event')
                            ->label('Evento')
                            ->badge(),
                        TextEntry::make('description')
                            ->label('Descrizione'),
                        TextEntry::make('causer.name')
                            ->label('Utente')
                            ->placeholder('Sistema'),
                        TextEntry::make('causer_id')
                            ->label('ID utente')
                            ->placeholder('-'),
                        TextEntry::make('subject_type')
                            ->label('Tipo oggetto')
                            ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                            ->placeholder('-'),
                        TextEntry::make('subject_id')
                            ->label('ID oggetto')
                            ->placeholder('-'),
                    ]),
                Section::make('Modifiche')
                    ->schema([
                        KeyValueEntry::make('attribute_changes.attributes')
                            ->label('Nuovi valori'),
                        KeyValueEntry::make('attribute_changes.old')
                            ->label('Valori precedenti'),
                    ]),
                Section::make('Proprieta')
                    ->schema([
                        KeyValueEntry::make('properties')
                            ->label('Dettagli'),
                    ])
                    ->collapsible(),
            ]);
    }
}
