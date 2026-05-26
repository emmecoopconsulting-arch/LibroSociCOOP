<?php

namespace App\Filament\Resources\Socios\Pages;

use App\Filament\Resources\Socios\SocioResource;
use App\Models\SocioWorkContract;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSocio extends CreateRecord
{
    protected static string $resource = SocioResource::class;

    private array $contractData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->contractData = $this->extractContractData($data);

        return $this->withoutContractData($data);
    }

    protected function afterCreate(): void
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

    private function saveWorkContract(Model $socio, array $contractData): void
    {
        if ($socio->tipologia !== 'lavoratore') {
            return;
        }

        SocioWorkContract::create([
            ...$contractData,
            'socio_id' => $socio->id,
            'stato' => 'attivo',
        ]);
    }
}
