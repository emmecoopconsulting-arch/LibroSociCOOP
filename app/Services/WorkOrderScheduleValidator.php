<?php

namespace App\Services;

use App\Models\Socio;
use App\Models\WorkAbsence;
use App\Models\WorkOrderSite;
use App\Models\WorkSiteAssignment;
use Illuminate\Validation\ValidationException;

class WorkOrderScheduleValidator
{
    public function validateOrderSite(WorkOrderSite $site): void
    {
        if (! $site->work_order_id) {
            return;
        }

        $socios = $this->sociosById($site->socio_ids ?? []);

        foreach ($socios as $socio) {
            $this->ensureWorkerIsNotAbsent($site, (int) $socio->id, $socio->nome_completo);
            $this->ensureWorkerHasNoOverlappingAssignment($site, (int) $socio->id, $socio->nome_completo);
        }
    }

    public function validateAssignment(WorkSiteAssignment $assignment): void
    {
        $assignment->loadMissing('orderSite.order', 'socio');
        $site = $assignment->orderSite;

        if (! $site || ! $site->work_order_id) {
            return;
        }

        $this->ensureWorkerIsNotAbsent($site, (int) $assignment->socio_id, $assignment->socio->nome_completo);
        $this->ensureWorkerHasNoOverlappingAssignment($site, (int) $assignment->socio_id, $assignment->socio->nome_completo);
    }

    public function validateAbsence(WorkAbsence $absence): void
    {
        $absence->loadMissing('order');

        $socioIds = $absence->socio_ids ?: array_filter([(int) $absence->socio_id]);

        foreach ($socioIds as $socioId) {
            $hasAssignment = WorkSiteAssignment::query()
                ->where('socio_id', $socioId)
                ->whereHas('orderSite', fn ($query) => $query->where('work_order_id', $absence->work_order_id))
                ->exists();

            $hasJsonAssignment = WorkOrderSite::query()
                ->where('work_order_id', $absence->work_order_id)
                ->get()
                ->contains(fn (WorkOrderSite $site): bool => in_array((int) $socioId, array_map('intval', $site->socio_ids ?? []), true));

            if ($hasAssignment || $hasJsonAssignment) {
                throw ValidationException::withMessages([
                    'socio_ids' => 'Una o piu persone assenti sono gia assegnate a un cantiere nell\'ordine di servizio.',
                ]);
            }
        }
    }

    private function ensureWorkerIsNotAbsent(WorkOrderSite $site, int $socioId, string $socioName): void
    {
        $absences = WorkAbsence::query()
            ->where('work_order_id', $site->work_order_id)
            ->get();

        $hasAbsence = $absences->contains(function (WorkAbsence $absence) use ($socioId): bool {
            $socioIds = $absence->socio_ids ?: array_filter([(int) $absence->socio_id]);

            return in_array($socioId, array_map('intval', $socioIds), true);
        });

        if ($hasAbsence) {
            throw ValidationException::withMessages([
                'socios' => "{$socioName} risulta assente nell'ordine di servizio.",
            ]);
        }
    }

    private function ensureWorkerHasNoOverlappingAssignment(WorkOrderSite $site, int $socioId, string $socioName): void
    {
        if (blank($site->orario_inizio) || blank($site->orario_fine)) {
            return;
        }

        $conflict = WorkOrderSite::query()
            ->where('work_order_id', $site->work_order_id)
            ->whereKeyNot($site->getKey())
            ->where('orario_inizio', '<', $site->orario_fine)
            ->where('orario_fine', '>', $site->orario_inizio)
            ->with('site')
            ->get()
            ->first(fn (WorkOrderSite $candidate): bool => in_array($socioId, array_map('intval', $candidate->socio_ids ?? []), true));

        if ($conflict) {
            throw ValidationException::withMessages([
                'socio_ids' => "{$socioName} e gia assegnato a {$conflict->displaySiteName()} nello stesso orario.",
            ]);
        }
    }

    private function sociosById(array $socioIds)
    {
        return Socio::query()
            ->whereIn('id', array_map('intval', $socioIds))
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();
    }
}
