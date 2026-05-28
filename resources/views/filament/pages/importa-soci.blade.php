<x-filament-panels::page>
    {{ $this->content }}

    <x-filament::section>
        <x-slot name="heading">
            Anteprima import
        </x-slot>

        @if ($preview)
            <div class="mb-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 px-4 py-3">
                    <div class="text-sm text-gray-500">Righe rilevate</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $preview['total_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                    <div class="text-sm text-green-700">Valide in anteprima</div>
                    <div class="mt-1 text-2xl font-semibold text-green-900">{{ $preview['valid_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                    <div class="text-sm text-red-700">Con errori in anteprima</div>
                    <div class="mt-1 text-2xl font-semibold text-red-900">{{ $preview['invalid_rows'] }}</div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-[1100px] w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 font-medium">Riga</th>
                            <th class="px-4 py-3 font-medium">Socio</th>
                            <th class="px-4 py-3 font-medium">Codice fiscale</th>
                            <th class="px-4 py-3 font-medium">Tipologia</th>
                            <th class="px-4 py-3 font-medium">Stato</th>
                            <th class="px-4 py-3 font-medium">Ammesso il</th>
                            <th class="px-4 py-3 font-medium">Esito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($preview['rows'] as $row)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $row['number'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{ trim(($row['data']['cognome'] ?? '') . ' ' . ($row['data']['nome'] ?? '')) ?: '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $row['data']['codice_fiscale'] ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['data']['tipologia'] ?: 'ordinario' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['data']['stato'] ?: 'attivo' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['data']['data_ammissione'] ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['errors'] === [])
                                        <span class="text-green-700">Pronta</span>
                                    @else
                                        <div class="space-y-1 text-red-700">
                                            @foreach ($row['errors'] as $error)
                                                <div>{{ $error }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="7">
                                    Nessuna riga dati trovata nel foglio selezionato.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                Carica un file Excel per vedere l’anteprima delle righe.
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
