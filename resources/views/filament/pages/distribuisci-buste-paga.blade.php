<x-filament-panels::page>
    {{ $this->content }}

    <style>
        .payroll-ui {
            --payroll-border: #d9dee7;
            --payroll-header: #f4f6f8;
            --payroll-hover: #fffaf2;
            --payroll-text: #344054;
            --payroll-primary: #d97706;
        }

        .payroll-ui table {
            width: 100% !important;
            min-width: 1050px !important;
            border-collapse: collapse !important;
            table-layout: auto !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
        }

        .payroll-table-shell {
            overflow: hidden;
            border: 1px solid var(--payroll-border);
            border-radius: 12px;
            background: white;
            box-shadow: 0 1px 3px rgba(16, 24, 40, .08);
        }

        .payroll-table-scroll {
            overflow-x: auto;
        }

        .payroll-ui table thead {
            background: var(--payroll-header) !important;
        }

        .payroll-ui table th {
            padding: 14px 16px !important;
            border-right: 1px solid var(--payroll-border) !important;
            border-bottom: 2px solid var(--payroll-border) !important;
            color: #475467 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: .035em !important;
            text-align: left !important;
            text-transform: uppercase !important;
            white-space: nowrap !important;
        }

        .payroll-ui table td {
            padding: 14px 16px !important;
            border-right: 1px solid #e9edf2 !important;
            border-bottom: 1px solid #e9edf2 !important;
            color: var(--payroll-text) !important;
            vertical-align: top !important;
        }

        .payroll-ui table th:last-child,
        .payroll-ui table td:last-child {
            border-right: 0 !important;
        }

        .payroll-ui table tbody tr:last-child td {
            border-bottom: 0 !important;
        }

        .payroll-ui table tbody tr:hover td {
            background: var(--payroll-hover) !important;
        }

        .payroll-ui table td > span {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 4px 10px !important;
            border-radius: 999px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            white-space: nowrap !important;
        }

        .payroll-ui .text-success-700 { color: #15803d !important; background: #ecfdf3 !important; }
        .payroll-ui .text-warning-700 { color: #b45309 !important; background: #fff7e6 !important; }
        .payroll-ui .text-danger-700 { color: #b42318 !important; background: #fef3f2 !important; }
        .payroll-ui .text-primary-700 { color: #9a5b05 !important; background: #fff7ed !important; }

        .payroll-ui table select {
            width: 100% !important;
            min-width: 320px !important;
            padding: 9px 34px 9px 11px !important;
            border: 1px solid #cfd6df !important;
            border-radius: 8px !important;
            color: #101828 !important;
            background: white !important;
        }

        .payroll-ui table button {
            padding: 7px 13px !important;
            border: 1px solid #f59e0b !important;
            border-radius: 8px !important;
            color: #9a5b05 !important;
            background: white !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            white-space: nowrap !important;
        }

        .payroll-ui table button:hover {
            background: #fff7ed !important;
        }

        .payroll-ui table a {
            display: block !important;
            margin-top: 5px !important;
            color: #b45309 !important;
            font-size: 12px !important;
            font-weight: 600 !important;
        }

        .payroll-ui table details {
            max-width: 360px !important;
            margin-top: 6px !important;
        }

        .payroll-ui table summary {
            cursor: pointer !important;
            color: #b45309 !important;
            font-size: 12px !important;
            font-weight: 600 !important;
        }

        .payroll-ui table pre {
            max-height: 190px !important;
            margin-top: 7px !important;
            padding: 9px !important;
            overflow: auto !important;
            border: 1px solid #e4e7ec !important;
            border-radius: 7px !important;
            background: #f9fafb !important;
            font-size: 11px !important;
            white-space: pre-wrap !important;
        }

        .payroll-progress-track {
            height: 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #e4e7ec;
        }

        .payroll-progress-card {
            margin-bottom: 20px;
            padding: 16px;
            border: 1px solid var(--payroll-border);
            border-radius: 12px;
            background: #f9fafb;
        }

        .payroll-progress-heading {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 9px;
            color: #344054;
            font-size: 14px;
        }

        .payroll-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #f59e0b, #d97706);
            transition: width .45s ease;
        }

        .payroll-error-panel {
            margin-bottom: 20px;
            padding: 16px;
            border: 1px solid #fecdca;
            border-radius: 12px;
            color: #7a271a;
            background: #fef3f2;
        }

        .payroll-error-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .payroll-retry-button {
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            color: white;
            background: #d97706;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .payroll-error-list {
            display: grid;
            gap: 8px;
            margin-top: 14px;
        }

        .payroll-error-item {
            padding: 11px 13px;
            border: 1px solid #fecdca;
            border-radius: 8px;
            background: white;
        }

        .payroll-error-message {
            margin-top: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        @media (prefers-color-scheme: dark) {
            .payroll-ui {
                --payroll-border: #374151;
                --payroll-header: #202631;
                --payroll-hover: #202631;
                --payroll-text: #e5e7eb;
            }

            .payroll-ui table select,
            .payroll-ui table button,
            .payroll-ui table pre {
                color: #e5e7eb !important;
                background: #111827 !important;
            }
        }
    </style>

    <div class="payroll-ui">
    <x-filament::section>
        <x-slot name="heading">Lavori di assegnazione</x-slot>
        <x-slot name="description">Ultime 50 distribuzioni caricate, con avanzamento delle associazioni e degli invii.</x-slot>

        <div class="payroll-table-shell">
            <div class="payroll-table-scroll">
            <table class="w-full min-w-[1050px] border-separate border-spacing-0 text-left text-sm">
                <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <tr>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Lavoro</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">File</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Assegnazioni</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Inviate</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Senza email</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Errori</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Stato</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Data</th>
                        <th class="border-b border-gray-200 px-5 py-4 dark:border-white/10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->distributions() as $item)
                        @php($statusLabel = ['processing' => 'Elaborazione', 'review' => 'Da verificare', 'sending' => 'Invio', 'partial' => 'Con errori', 'sent' => 'Completato', 'failed' => 'OCR fallito'][$item->status] ?? ucfirst($item->status))
                        <tr wire:key="distribution-{{ $item->id }}" class="transition hover:bg-gray-50 dark:hover:bg-white/5 {{ $distributionId === $item->id ? 'bg-primary-50 dark:bg-primary-950/20' : '' }}">
                            <td class="border-b border-r border-gray-100 px-5 py-4 font-semibold text-gray-950 dark:border-white/10 dark:text-white">Buste {{ $item->period ?: '#' . $item->id }}</td>
                            <td class="max-w-xs truncate border-b border-r border-gray-100 px-5 py-4 text-gray-600 dark:border-white/10 dark:text-gray-300" title="{{ $item->original_name }}">{{ $item->original_name }}</td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10">
                                <span class="inline-flex min-w-14 justify-center rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $item->assigned_pages_count }}/{{ $item->pages_count }}</span>
                            </td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-success-50 px-3 py-1 font-semibold text-success-700 dark:bg-success-400/10">{{ $item->sent_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-warning-50 px-3 py-1 font-semibold text-warning-700 dark:bg-warning-400/10">{{ $item->skipped_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-danger-50 px-3 py-1 font-semibold text-danger-700 dark:bg-danger-400/10">{{ $item->failed_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold {{ $item->status === 'sent' ? 'bg-success-50 text-success-700' : ($item->status === 'partial' || $item->status === 'failed' ? 'bg-danger-50 text-danger-700' : 'bg-primary-50 text-primary-700') }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap border-b border-r border-gray-100 px-5 py-4 text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-right dark:border-white/10">
                                <button
                                    type="button"
                                    class="rounded-lg border border-primary-300 bg-white px-3 py-1.5 font-semibold text-primary-700 shadow-sm transition hover:bg-primary-50 dark:bg-gray-900"
                                    wire:click="selectDistribution({{ $item->id }})"
                                >
                                    Apri
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nessun lavoro caricato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </x-filament::section>

    @php($distribution = $this->currentDistribution())

    @if ($distribution)
        <x-filament::section>
            <x-slot name="heading">Revisione associazioni</x-slot>
            <x-slot name="description">
                {{ $distribution->original_name }} — {{ $distribution->period }}.
                L’invio è consentito solo quando ogni pagina è associata.
            </x-slot>

            @if ($distribution->error)
                <div class="mb-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                    {{ $distribution->error }}
                </div>
            @endif

            @php($totalRecipients = $distribution->pages->whereNotNull('socio_id')->pluck('socio_id')->unique()->count())
            @php($processedRecipients = $distribution->sent_count + $distribution->failed_count + $distribution->skipped_count)
            @php($progress = $totalRecipients > 0 ? min(100, (int) round(($processedRecipients / $totalRecipients) * 100)) : 0)

            <div class="payroll-progress-card">
                <div class="payroll-progress-heading">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">Avanzamento invio</span>
                    <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ $processedRecipients }}/{{ $totalRecipients }} destinatari · {{ $progress }}%</span>
                </div>
                <div class="payroll-progress-track">
                    <div class="payroll-progress-fill" style="width: {{ $progress }}%"></div>
                </div>
                <div wire:loading wire:target="distribute,retryFailed" class="mt-3 w-full">
                    <div class="mb-1 text-xs font-medium text-primary-700">Invio in corso, non chiudere la pagina…</div>
                    <div class="h-2 overflow-hidden rounded-full bg-primary-100">
                        <div class="h-full w-full animate-pulse rounded-full bg-primary-500"></div>
                    </div>
                </div>
            </div>

            @if ($distribution->failed_count > 0)
                <div class="payroll-error-panel">
                    <div class="payroll-error-heading">
                        <div>
                            <div class="font-semibold text-danger-800 dark:text-danger-300">{{ $distribution->failed_count }} invii non riusciti</div>
                            <div class="mt-1 text-sm text-danger-700 dark:text-danger-300">Controlla le motivazioni e riprova soltanto le email fallite.</div>
                        </div>
                        <button type="button" wire:click="retryFailed" wire:loading.attr="disabled" wire:target="retryFailed" class="payroll-retry-button">
                            Riprova fallite
                        </button>
                    </div>
                    <div class="payroll-error-list">
                        @foreach ($distribution->deliveries->where('status', 'failed') as $failedDelivery)
                            <div class="payroll-error-item">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $failedDelivery->socio?->nome_completo ?: 'Socio non disponibile' }} — {{ $failedDelivery->email }}</div>
                                <div class="payroll-error-message">{{ $failedDelivery->error ?: 'Il server SMTP non ha restituito una motivazione.' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="payroll-table-shell">
                <div class="payroll-table-scroll">
                <table class="w-full min-w-[1050px] border-separate border-spacing-0 text-left text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        <tr>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Pagina</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Socio destinatario</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Affidabilità</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Motivo</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Consegna</th>
                            <th class="border-b border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Testo OCR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($distribution->pages as $page)
                            <tr wire:key="payroll-page-{{ $page->id }}" class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="border-b border-r border-gray-100 px-5 py-4 text-center font-bold dark:border-white/10">{{ $page->page_number }}</td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                    <select
                                        class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/20 dark:bg-gray-900"
                                        wire:change="setSocio({{ $page->id }}, $event.target.value)"
                                        @disabled($distribution->deliveries->contains('status', 'sent'))
                                    >
                                        <option value="">— Da associare —</option>
                                        @foreach ($this->socioOptions() as $id => $label)
                                            <option value="{{ $id }}" @selected((int) $page->socio_id === (int) $id)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                    <span class="inline-flex rounded-full px-3 py-1 font-semibold {{ $page->match_confidence >= 95 ? 'bg-success-50 text-success-700' : ($page->match_confidence >= 80 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700') }}">
                                        {{ $page->match_confidence }}%
                                    </span>
                                </td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">{{ $page->match_reason ?: '—' }}</td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                    @php($delivery = $distribution->deliveries->firstWhere('socio_id', $page->socio_id))
                                    @php($document = $distribution->documents->firstWhere('socio_id', $page->socio_id))
                                    @if (! $page->socio_id)
                                        <span class="text-danger-700">Da assegnare</span>
                                    @elseif ($delivery?->status === 'sent')
                                        <span class="text-success-700">Email inviata</span>
                                    @elseif ($delivery?->status === 'failed')
                                        <span class="inline-flex rounded-full bg-danger-50 px-3 py-1 font-semibold text-danger-700">Errore invio</span>
                                        <details class="mt-2 max-w-sm">
                                            <summary class="cursor-pointer text-xs font-semibold text-danger-700">Mostra motivazione</summary>
                                            <div class="mt-1 break-words rounded-md bg-danger-50 p-2 font-mono text-xs text-danger-800">{{ $delivery->error ?: 'Motivazione non disponibile.' }}</div>
                                        </details>
                                    @elseif ($delivery?->status === 'skipped_no_email' || blank($page->socio?->email))
                                        <span class="text-warning-700">Archiviata, senza email</span>
                                    @else
                                        <span>Archiviata nei documenti</span>
                                    @endif
                                    @if ($document)
                                        <a
                                            class="mt-1 block text-xs text-primary-600 hover:underline"
                                            href="{{ route('local-files.file', ['path' => \Illuminate\Support\Facades\Crypt::encryptString($document->file_path), 'download' => true]) }}"
                                            target="_blank"
                                        >
                                            Scarica PDF
                                        </a>
                                    @endif
                                </td>
                                <td class="max-w-sm border-b border-gray-100 px-5 py-4 dark:border-white/10">
                                    <details>
                                        <summary class="cursor-pointer text-primary-600">Mostra testo</summary>
                                        <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap text-xs">{{ $page->ocr_text }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            @if ($distribution->deliveries->isNotEmpty())
                <div class="mt-5 text-sm">
                    Inviate: {{ $distribution->deliveries->where('status', 'sent')->count() }} ·
                    Errori: {{ $distribution->deliveries->where('status', 'failed')->count() }}
                </div>
            @endif
        </x-filament::section>
    @endif
    </div>
</x-filament-panels::page>
