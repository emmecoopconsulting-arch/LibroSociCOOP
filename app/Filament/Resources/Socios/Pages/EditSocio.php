<?php

namespace App\Filament\Resources\Socios\Pages;

use App\Filament\Resources\Socios\SocioResource;
use App\Models\SocioWorkContract;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSocio extends EditRecord
{
    protected static string $resource = SocioResource::class;

    private array $contractData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $contract = $this->record->workContracts()
            ->where('stato', 'attivo')
            ->latest('data_inizio')
            ->latest('id')
            ->first();

        if (! $contract) {
            return $data;
        }

        return [
            ...$data,
            'contract_tipo_contratto' => $contract->tipo_contratto,
            'contract_data_inizio' => $contract->data_inizio,
            'contract_data_fine' => $contract->data_fine,
            'contract_ore_settimanali' => $contract->ore_settimanali,
            'contract_note' => $contract->note,
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->contractData = $this->extractContractData($data);

        return $this->withoutContractData($data);
    }

    protected function afterSave(): void
    {
        $this->saveWorkContract($this->record, $this->contractData);
    }

    private function extractContractData(array $data): array
    {
        $tipoContratto = $data['contract_tipo_contratto'] ?? null;

        return [
            'tipo_contratto' => $tipoContratto,
            'data_inizio' => $data['contract_data_inizio'] ?? null,
            'data_fine' => $tipoContratto === 'determinato' ? ($data['contract_data_fine'] ?? null) : null,
            'ore_settimanali' => $data['contract_ore_settimanali'] ?? null,
            'note' => $data['contract_note'] ?? null,
        ];
    }

    private function withoutContractData(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'contract_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function saveWorkContract($socio, array $contractData): void
    {
        if ($socio->tipologia !== 'lavoratore') {
            return;
        }

        $socio->workContracts()
            ->where('stato', 'attivo')
            ->latest('data_inizio')
            ->latest('id')
            ->first()
            ?->update($contractData)
            ?? SocioWorkContract::create([
                ...$contractData,
                'socio_id' => $socio->id,
                'stato' => 'attivo',
            ]);
    }
}
