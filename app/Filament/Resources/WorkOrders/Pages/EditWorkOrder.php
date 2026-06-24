<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Services\WorkOrderPdfService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scaricaPdf')
                ->label('Scarica PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => app(WorkOrderPdfService::class)->downloadResponse($this->record, regenerate: true)),
            Action::make('archiviaPdf')
                ->label('Scarica e archivia')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->requiresConfirmation()
                ->action(fn () => app(WorkOrderPdfService::class)->downloadResponse($this->record, regenerate: true, archive: true)),
            DeleteAction::make(),
        ];
    }
}
