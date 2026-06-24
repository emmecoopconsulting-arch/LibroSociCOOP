<?php

namespace App\Services;

use App\Models\WorkAbsence;
use App\Models\WorkSite;
use App\Models\WorkSiteAssignment;
use Illuminate\Validation\ValidationException;

class WorkOrderScheduleValidator
{
    public function validateSite(WorkSite $site): void
    {
        $site->loadMissing('assignments.socio');

        if ($site->orario_inizio >= $site->orario_fine) {
            throw ValidationException::withMessages([
                'orario_fine' => 'L\'orario di fine deve essere successivo all\'orario di inizio.',
            ]);
        }

        foreach ($site->assignments as $assignment) {
            $this->ensureWorkerIsNotAbsent($site, $assignment);
            $this->ensureWorkerHasNoOverlappingAssignment($site, $assignment);
        }
    }

    public function validateAssignment(WorkSiteAssignment $assignment): void
    {
        $assignment->loadMissing('site.order', 'socio');
        $site = $assignment->site;

        if (! $site || ! $site->work_order_id) {
            return;
        }

        $this->ensureWorkerIsNotAbsent($site, $assignment);
        $this->ensureWorkerHasNoOverlappingAssignment($site, $assignment);
    }

    public function validateAbsence(WorkAbsence $absence): void
    {
        $absence->loadMissing('socio');

        $hasAssignment = WorkSiteAssignment::query()
            ->where('socio_id', $absence->socio_id)
            ->whereHas('site', fn ($query) => $query->where('work_order_id', $absence->work_order_id))
            ->exists();

        if ($hasAssignment) {
            throw ValidationException::withMessages([
                'socio_id' => "{$absence->socio->nome_completo} e gia assegnato a un cantiere nell'ordine di servizio.",
            ]);
        }
    }

    private function ensureWorkerIsNotAbsent(WorkSite $site, WorkSiteAssignment $assignment): void
    {
        $hasAbsence = WorkAbsence::query()
            ->where('work_order_id', $site->work_order_id)
            ->where('socio_id', $assignment->socio_id)
            ->exists();

        if ($hasAbsence) {
            throw ValidationException::withMessages([
                'socio_id' => "{$assignment->socio->nome_completo} risulta assente nell'ordine di servizio.",
            ]);
        }
    }

    private function ensureWorkerHasNoOverlappingAssignment(WorkSite $site, WorkSiteAssignment $assignment): void
    {
        $conflictQuery = WorkSiteAssignment::query()
            ->where('socio_id', $assignment->socio_id)
            ->whereHas('site', function ($query) use ($site): void {
                $query
                    ->where('work_order_id', $site->work_order_id)
                    ->whereKeyNot($site->getKey())
                    ->where('orario_inizio', '<', $site->orario_fine)
                    ->where('orario_fine', '>', $site->orario_inizio);
            })
            ->with('site');

        if ($assignment->exists) {
            $conflictQuery->whereKeyNot($assignment->getKey());
        }

        $conflict = $conflictQuery->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'socio_id' => "{$assignment->socio->nome_completo} e gia assegnato a {$conflict->site->nome} nello stesso orario.",
            ]);
        }
    }
}
