<x-filament-panels::page>
    @php($overview = $this->overview())
    <x-filament::section heading="Controllo visite mediche" description="Riepilogo dei soci ordinari attivi. Gli avvisi includono le scadenze di oggi e dei prossimi {{ $this->alertDays() }} giorni.">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
            @foreach (\App\Filament\Pages\VisiteMediche::VISIT_STATUSES + ['without_pdf' => 'Ultima visita senza PDF'] as $status => $label)
                <button type="button" wire:click="selectOverviewFilter('{{ $status }}')" class="rounded-lg p-3 text-left ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-800" aria-label="Mostra soci: {{ $label }}">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="text-3xl font-semibold">{{ $overview[$status] }}</p>
                </button>
            @endforeach
        </div>
    </x-filament::section>

    {{ $this->table }}

    <div id="visit-registration" x-on:scroll-to-visit-form.window="$el.scrollIntoView({ behavior: 'smooth' })">
        {{ $this->content }}
    </div>
</x-filament-panels::page>
