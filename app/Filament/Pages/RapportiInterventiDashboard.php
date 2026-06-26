<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use App\Models\WorkReport;
use App\Models\WorkSite;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class RapportiInterventiDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Dashboard rapporti';

    protected static ?string $title = 'Dashboard rapporti interventi';

    protected static ?string $navigationParentItem = 'Gestione Lavori';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.rapporti-interventi-dashboard';

    public string $dateFrom;

    public string $dateTo;

    public ?int $workSiteId = null;

    public ?int $socioId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->workSiteId = null;
        $this->socioId = null;
    }

    public function totalHours(): float
    {
        return $this->filteredReports()->sum(fn (WorkReport $report): float => (float) $report->total_hours);
    }

    public function reportsCount(): int
    {
        return $this->filteredReports()->count();
    }

    public function operatorsCount(): int
    {
        return $this->filteredReports()
            ->flatMap(fn (WorkReport $report): array => $report->socio_ids ?? [])
            ->unique()
            ->count();
    }

    public function siteOptions(): Collection
    {
        return WorkSite::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (WorkSite $site): array => [
                'id' => $site->id,
                'label' => $site->display_name,
            ]);
    }

    public function operatorOptions(): Collection
    {
        return Socio::query()
            ->attivi()
            ->where('tipologia', 'ordinario')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->map(fn (Socio $socio): array => [
                'id' => $socio->id,
                'label' => "{$socio->cognome} {$socio->nome}",
            ]);
    }

    public function hoursByClient(): Collection
    {
        return $this->filteredReports()
            ->groupBy(fn (WorkReport $report): string => $report->displaySiteName())
            ->map(fn (Collection $reports, string $siteName): array => [
                'label' => $siteName,
                'hours' => $reports->sum(fn (WorkReport $report): float => (float) $report->total_hours),
                'reports' => $reports->count(),
            ])
            ->sortByDesc('hours')
            ->values();
    }

    public function hoursByOperator(): Collection
    {
        $totals = collect();

        foreach ($this->filteredReports() as $report) {
            foreach ($report->operator_hours ?? [] as $row) {
                if (blank($row['socio_id'] ?? null)) {
                    continue;
                }

                if ($this->socioId && (int) $row['socio_id'] !== $this->socioId) {
                    continue;
                }

                $socioId = (int) $row['socio_id'];
                $totals[$socioId] = ($totals[$socioId] ?? 0) + (float) ($row['hours'] ?? 0);
            }
        }

        if ($totals->isEmpty()) {
            return collect();
        }

        $socios = Socio::query()
            ->whereIn('id', $totals->keys()->all())
            ->get()
            ->keyBy('id');

        return $totals
            ->map(fn (float $hours, int $socioId): array => [
                'label' => filled($socios[$socioId] ?? null)
                    ? "{$socios[$socioId]->cognome} {$socios[$socioId]->nome}"
                    : "Operatore #{$socioId}",
                'hours' => $hours,
            ])
            ->sortByDesc('hours')
            ->values();
    }

    public function reportRows(): Collection
    {
        return $this->filteredReports()
            ->sortByDesc('data_intervento')
            ->map(fn (WorkReport $report): array => [
                'date' => $report->data_intervento?->format('d/m/Y') ?? '',
                'protocollo' => $report->protocollo,
                'site' => $report->displaySiteName(),
                'object' => $report->oggetto,
                'hours' => (float) $report->total_hours,
                'operators' => $report->operatorHoursBySocio()
                    ->map(fn (array $row): string => "{$row['socio']->cognome} {$row['socio']->nome} (".number_format((float) $row['hours'], 2, ',', '.')." ore)")
                    ->join(', '),
            ])
            ->values();
    }

    private function filteredReports(): Collection
    {
        $dateFrom = CarbonImmutable::parse($this->dateFrom)->startOfDay();
        $dateTo = CarbonImmutable::parse($this->dateTo)->endOfDay();

        return WorkReport::query()
            ->whereBetween('data_intervento', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($this->workSiteId, fn ($query): mixed => $query->where('work_site_id', $this->workSiteId))
            ->orderByDesc('data_intervento')
            ->get()
            ->filter(function (WorkReport $report): bool {
                if (! $this->socioId) {
                    return true;
                }

                return in_array($this->socioId, array_map('intval', $report->socio_ids ?? []), true);
            })
            ->values();
    }
}
