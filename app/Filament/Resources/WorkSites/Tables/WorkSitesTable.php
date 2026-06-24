<?php

namespace App\Filament\Resources\WorkSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->label('Cantiere')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('luogo')
                    ->label('Luogo')
                    ->searchable(),
                TextColumn::make('order_sites_count')
                    ->label('Ordini')
                    ->counts('orderSites')
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(60)
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
