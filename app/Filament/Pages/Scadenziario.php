<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use App\Models\SocioWorkContract;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class Scadenziario extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Scadenziario';

    protected static ?string $title = 'Scadenziario';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.scadenziario';

    public string $month;

    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->month = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->month = $this->currentMonth()->subMonth()->toDateString();
        $this->selectedDate = null;
    }

    public function nextMonth(): void
    {
        $this->month = $this->currentMonth()->addMonth()->toDateString();
        $this->selectedDate = null;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month)->startOfMonth();
    }

    /**
     * @return array<int, array<int, CarbonImmutable|null>>
     */
    public function weeks(): array
    {
        $month = $this->currentMonth();
        $cursor = $month->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $weeks = [];

        while ($cursor->lte($end)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->month === $month->month ? $cursor : null;
                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return array<string, array<int, array{label: string, type: string, date: string}>>
     */
    public function eventsByDay(): array
    {
        $start = $this->currentMonth();
        $end = $start->endOfMonth();

        return $this->deadlineEvents($start, $end)
            ->groupBy('date')
            ->map(fn (Collection $events): array => $events->values()->all())
            ->all();
    }

    public function upcomingDeadlines(): Collection
    {
        $start = CarbonImmutable::today();

        return $this->deadlineEvents(CarbonImmutable::create(1900, 1, 1), $start->addDays(90))->take(12);
    }

    public function selectedDayEvents(): Collection
    {
        if (! $this->selectedDate) {
            return collect();
        }

        return collect($this->eventsByDay()[$this->selectedDate] ?? []);
    }

    private function deadlineEvents(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $permessi = Socio::query()
            ->sociEffettivi()
            ->where('ha_permesso_soggiorno', true)
            ->whereBetween('scadenza_permesso_soggiorno', [$start, $end])
            ->orderBy('scadenza_permesso_soggiorno')
            ->get()
            ->map(fn (Socio $socio): array => [
                'label' => $socio->nome_completo.' - permesso soggiorno',
                'type' => 'permesso',
                'date' => $socio->scadenza_permesso_soggiorno->toDateString(),
                'is_overdue' => $socio->scadenza_permesso_soggiorno->lt(CarbonImmutable::today()),
            ]);

        $contratti = SocioWorkContract::query()
            ->with('socio')
            ->where('stato', 'attivo')
            ->whereBetween('data_fine', [$start, $end])
            ->orderBy('data_fine')
            ->get()
            ->map(fn (SocioWorkContract $contract): array => [
                'label' => $contract->socio?->nome_completo.' - contratto',
                'type' => 'contratto',
                'date' => $contract->data_fine->toDateString(),
                'is_overdue' => $contract->data_fine->lt(CarbonImmutable::today()),
            ]);

        $visite = SocioMedicalVisit::query()
            ->with('socio')
            ->whereIn('id', SocioMedicalVisit::query()
                ->selectRaw('MAX(id)')
                ->groupBy('socio_id'))
            ->whereBetween('scadenza', [$start, $end])
            ->orderBy('scadenza')
            ->get()
            ->map(fn (SocioMedicalVisit $visit): array => [
                'label' => $visit->socio?->nome_completo.' - visita medica',
                'type' => 'visita',
                'date' => $visit->scadenza->toDateString(),
                'is_overdue' => $visit->scadenza->lt(CarbonImmutable::today()),
            ]);

        return $permessi
            ->concat($contratti)
            ->concat($visite)
            ->filter(fn (array $event): bool => filled($event['label']))
            ->sortBy('date')
            ->values();
    }
}
