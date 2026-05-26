<?php

namespace App\Filament\Widgets;

use App\Models\Socio;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SociOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Riepilogo soci';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $capitaleSociale = (float) Socio::sum('capitale_versato');

        return [
            Stat::make('Soci totali', Socio::count())
                ->description('Iscritti nel libro soci')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Soci attivi', Socio::attivi()->count())
                ->description('Stato attivo')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Soci ordinari', Socio::where('tipologia', 'ordinario')->count())
                ->description('Tipologia ordinario')
                ->icon('heroicon-o-identification')
                ->color('info'),
            Stat::make('Capitale sociale', $this->formatEuro($capitaleSociale))
                ->description('Somma dei versamenti')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }

    private function formatEuro(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' EUR';
    }
}
