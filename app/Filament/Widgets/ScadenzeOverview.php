<?php

namespace App\Filament\Widgets;

use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class ScadenzeOverview extends Widget
{
    protected string $view = 'filament.widgets.scadenze-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        return [
            'permessoAlertDays' => AppSetting::int(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS),
            'visitaAlertDays' => AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS),
            'permessi' => $this->permessiInScadenza(),
            'visite' => $this->visiteInScadenza(),
            'permessiScaduti' => $this->permessiScadutiCount(),
            'visiteScadute' => $this->visiteScaduteCount(),
        ];
    }

    private function permessiInScadenza(): Collection
    {
        $today = CarbonImmutable::today();
        $alertDays = AppSetting::int(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS);

        return Socio::query()
            ->where('ha_permesso_soggiorno', true)
            ->whereDate('scadenza_permesso_soggiorno', '<=', $today->addDays($alertDays))
            ->orderBy('scadenza_permesso_soggiorno')
            ->limit(8)
            ->get();
    }

    private function visiteInScadenza(): Collection
    {
        $today = CarbonImmutable::today();
        $alertDays = AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS);

        return SocioMedicalVisit::query()
            ->with('socio')
            ->whereIn('id', SocioMedicalVisit::query()
                ->selectRaw('MAX(id)')
                ->groupBy('socio_id'))
            ->whereDate('scadenza', '<=', $today->addDays($alertDays))
            ->orderBy('scadenza')
            ->limit(8)
            ->get();
    }

    private function permessiScadutiCount(): int
    {
        return Socio::query()
            ->where('ha_permesso_soggiorno', true)
            ->whereDate('scadenza_permesso_soggiorno', '<', CarbonImmutable::today())
            ->count();
    }

    private function visiteScaduteCount(): int
    {
        return SocioMedicalVisit::query()
            ->whereIn('id', SocioMedicalVisit::query()
                ->selectRaw('MAX(id)')
                ->groupBy('socio_id'))
            ->whereDate('scadenza', '<', CarbonImmutable::today())
            ->count();
    }
}
