<?php

namespace App\Filament\Resources\WorkReports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_intervento', 'desc')
            ->columns([
                TextColumn::make('protocollo')
                    ->label('Protocollo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_intervento')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('site.nome')
                    ->label('Cantiere')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('oggetto')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('order.titolo')
                    ->label('Ordine di servizio')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
