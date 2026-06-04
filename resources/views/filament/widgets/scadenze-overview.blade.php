<x-filament-widgets::widget>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-amber-950 dark:text-amber-100">Permessi di soggiorno in scadenza</h3>
                <span class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ $permessiScaduti }} scaduti / {{ $permessoAlertDays }} giorni</span>
            </div>

            <div class="space-y-2">
                @forelse ($permessi as $socio)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-white/70 px-3 py-2 text-sm ring-1 ring-amber-200 dark:bg-white/5 dark:ring-amber-500/20">
                        <span class="font-medium text-amber-950 dark:text-amber-100">{{ $socio->nome_completo }}</span>
                        <span class="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                            @if ($socio->scadenza_permesso_soggiorno->lt(\Carbon\CarbonImmutable::today()))
                                <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">Scaduto</span>
                            @endif
                            {{ $socio->scadenza_permesso_soggiorno->format('d/m/Y') }}
                        </span>
                    </div>
                @empty
                    <div class="text-sm text-amber-800 dark:text-amber-200">Nessun permesso in scadenza.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-emerald-950 dark:text-emerald-100">Visite mediche in scadenza</h3>
                <span class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ $visiteScadute }} scadute / {{ $visitaAlertDays }} giorni</span>
            </div>

            <div class="space-y-2">
                @forelse ($visite as $visita)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-white/70 px-3 py-2 text-sm ring-1 ring-emerald-200 dark:bg-white/5 dark:ring-emerald-500/20">
                        <span class="font-medium text-emerald-950 dark:text-emerald-100">{{ $visita->socio?->nome_completo }}</span>
                        <span class="flex items-center gap-2 text-emerald-800 dark:text-emerald-200">
                            @if ($visita->scadenza->lt(\Carbon\CarbonImmutable::today()))
                                <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">Scaduta</span>
                            @endif
                            {{ $visita->scadenza->format('d/m/Y') }}
                        </span>
                    </div>
                @empty
                    <div class="text-sm text-emerald-800 dark:text-emerald-200">Nessuna visita medica in scadenza.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
