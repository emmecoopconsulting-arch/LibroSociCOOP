<x-filament-panels::page>
    @php($stats = $this->presenzaStats())

    @if ($this->currentAssembleaId)
        <x-filament::section>
            <x-slot name="heading">
                Assemblea in corso
            </x-slot>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500">Presenti</div>
                    <div class="text-2xl font-semibold">{{ $stats['presenti'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Deleghe</div>
                    <div class="text-2xl font-semibold">{{ $stats['deleghe'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Assenti</div>
                    <div class="text-2xl font-semibold">{{ $stats['assenti'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Totale appello</div>
                    <div class="text-2xl font-semibold">{{ $stats['totale'] }}</div>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->content }}

    <x-filament::section>
        <x-slot name="heading">
            Assemblee
        </x-slot>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-[980px] w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 font-medium">Data</th>
                        <th class="px-4 py-3 font-medium">Titolo</th>
                        <th class="px-4 py-3 font-medium">Stato</th>
                        <th class="px-4 py-3 font-medium">Appello</th>
                        <th class="px-4 py-3 font-medium">ODG</th>
                        <th class="px-4 py-3 font-medium">Variazioni</th>
                        <th class="px-4 py-3 font-medium">Apertura</th>
                        <th class="px-4 py-3 font-medium">Chiusura</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->recentAssemblee() as $assemblea)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $assemblea->data_assemblea?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $assemblea->titolo }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ \App\Models\Assemblea::STATI[$assemblea->stato] ?? $assemblea->stato }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->presenze_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->punti_odg_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->variations_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->started_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->closed_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($assemblea->file_path)
                                    <x-filament::button tag="a" size="sm" icon="heroicon-o-document-arrow-down" :href="route('assemblee.download', $assemblea)">
                                        Scarica verbale
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-center text-gray-500" colspan="5">
                                Nessuna assemblea generata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
