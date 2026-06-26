<?php

namespace App\Filament\Resources\WorkReports\Pages;

use App\Filament\Resources\WorkReports\WorkReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkReport extends EditRecord
{
    protected static string $resource = WorkReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (blank($data['operator_hours'] ?? null) && filled($data['socio_ids'] ?? null)) {
            $data['operator_hours'] = collect($data['socio_ids'])
                ->filter()
                ->map(fn ($socioId): array => [
                    'socio_id' => (int) $socioId,
                    'hours' => 0,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
