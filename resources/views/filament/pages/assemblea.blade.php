<x-filament-panels::page>
    {{ $this->content }}

    <x-filament::section>
        <x-slot name="heading">
            Assemblee generate
        </x-slot>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-[860px] w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 font-medium">Data</th>
                        <th class="px-4 py-3 font-medium">Titolo</th>
                        <th class="px-4 py-3 font-medium">Variazioni</th>
                        <th class="px-4 py-3 font-medium">Generato il</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->recentAssemblee() as $assemblea)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $assemblea->data_assemblea?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $assemblea->titolo }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->variations_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $assemblea->generato_il?->format('d/m/Y H:i') ?: '-' }}</td>
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
