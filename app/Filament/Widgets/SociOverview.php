<?php

namespace App\Filament\Widgets;

use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SociOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Riepilogo soci';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $capitaleSociale = (float) Socio::query()->sociEffettivi()->sum('capitale_versato');
        $today = CarbonImmutable::today();
        $permessoAlertDays = AppSetting::int(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS);
        $visitaAlertDays = AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS);
        $permessiScaduti = Socio::query()
            ->sociEffettivi()
            ->where('ha_permesso_soggiorno', true)
            ->whereDate('scadenza_permesso_soggiorno', '<', $today)
            ->count();
        $visiteScadute = SocioMedicalVisit::query()
            ->whereIn('id', SocioMedicalVisit::query()
                ->selectRaw('MAX(id)')
                ->groupBy('socio_id'))
            ->whereDate('scadenza', '<', $today)
            ->count();

        return [
            Stat::make('Soci totali', Socio::query()->sociEffettivi()->count())
                ->description('Iscritti nel libro soci')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Soci attivi', Socio::query()->sociEffettivi()->attivi()->count())
                ->description('Stato attivo')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Soci ordinari', Socio::query()->ordinari()->count())
                ->description('Tipologia ordinario')
                ->icon('heroicon-o-identification')
                ->color('info'),
            Stat::make('Capitale sociale', $this->formatEuro($capitaleSociale))
                ->description('Somma dei versamenti')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
            Stat::make('Permessi in scadenza', Socio::query()
                ->sociEffettivi()
                ->where('ha_permesso_soggiorno', true)
                ->whereDate('scadenza_permesso_soggiorno', '<=', $today->addDays($permessoAlertDays))
                ->count())
                ->description("{$permessiScaduti} scaduti / entro {$permessoAlertDays} giorni")
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make('Visite mediche in scadenza', SocioMedicalVisit::query()
                ->whereIn('id', SocioMedicalVisit::query()
                    ->selectRaw('MAX(id)')
                    ->groupBy('socio_id'))
                ->whereDate('scadenza', '<=', $today->addDays($visitaAlertDays))
                ->count())
                ->description("{$visiteScadute} scadute / entro {$visitaAlertDays} giorni")
                ->icon('heroicon-o-heart')
                ->color('warning'),
        ];
    }

    private function formatEuro(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' EUR';
    }
}
