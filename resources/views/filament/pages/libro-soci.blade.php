<x-filament-panels::page>
    <x-filament::section>
        <div class="mb-4 flex justify-end gap-2">
            <x-filament::button tag="a" :href="route('exports.libro-soci.pdf')" icon="heroicon-o-document-arrow-down">
                PDF
            </x-filament::button>
            <x-filament::button tag="a" :href="route('exports.libro-soci.excel')" icon="heroicon-o-table-cells">
                Excel
            </x-filament::button>
        </div>

        <div class="rounded-lg border border-gray-200">
            <div class="hidden grid-cols-[120px_1.4fr_160px_120px_110px_120px] gap-4 border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 lg:grid">
                <div>Codice</div>
                <div>Socio</div>
                <div>Codice fiscale</div>
                <div>Tipologia</div>
                <div>Ammesso il</div>
                <div class="text-right">Capitale</div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($this->soci() as $socio)
                    <div class="grid gap-2 px-4 py-4 text-sm lg:grid-cols-[120px_1.4fr_160px_120px_110px_120px] lg:items-center lg:gap-4">
                        <div class="font-medium text-gray-950">{{ $socio->codice_socio }}</div>
                        <div>
                            <div class="font-medium text-gray-950">{{ $socio->cognome }} {{ $socio->nome }}</div>
                            <div class="text-xs text-gray-500 lg:hidden">{{ $socio->codice_fiscale }}</div>
                        </div>
                        <div class="hidden font-mono text-xs text-gray-700 lg:block">{{ $socio->codice_fiscale }}</div>
                        <div class="text-gray-700">{{ \App\Models\Socio::TIPOLOGIE[$socio->tipologia] ?? $socio->tipologia }}</div>
                        <div class="text-gray-700">{{ $socio->data_ammissione?->format('d/m/Y') ?: '-' }}</div>
                        <div class="font-medium text-gray-950 lg:text-right">{{ number_format((float) $socio->capitale_versato, 2, ',', '.') }} EUR</div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        Nessun socio attivo presente.
                    </div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
