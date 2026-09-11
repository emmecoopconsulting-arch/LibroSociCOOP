<?php

namespace App\Filament\Actions;

use App\Services\TableExcelExportService;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTableExcelAction
{
    public static function make(string $title, string $filename): Action
    {
        return Action::make('exportExcel')
            ->label('Esporta Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->tooltip('Esporta tutte le righe filtrate con le colonne visibili')
            ->action(fn (HasTable $livewire): StreamedResponse => app(TableExcelExportService::class)->download($livewire, $title, $filename));
    }
}
