<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Area')
                    ->badge()
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('causer.name')
                    ->label('Utente')
                    ->placeholder('Sistema')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Oggetto')
                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                    ->toggleable(),
                TextColumn::make('subject_id')
                    ->label('ID oggetto')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Area')
                    ->options(fn (): array => self::distinctOptions('log_name')),
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options(fn (): array => self::distinctOptions('event')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    private static function distinctOptions(string $column): array
    {
        return Activity::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }
}
