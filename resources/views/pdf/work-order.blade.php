<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 92px 24px 46px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.45; }
        h1 { color: #92400e; font-size: 22px; margin: 0 0 8px; }
        h2 { color: #075985; font-size: 17px; margin: 20px 0 10px; border-bottom: 2px solid #bae6fd; padding-bottom: 4px; }
        h3 { color: #92400e; font-size: 15px; margin: 0 0 6px; }
        p { margin: 0 0 8px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #fef3c7; color: #78350f; font-weight: bold; }
        .document-header { position: fixed; top: -70px; left: 0; right: 0; height: 52px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 42px; max-width: 140px; margin-right: 14px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
        .muted { color: #6b7280; }
        .site { page-break-inside: avoid; margin-bottom: 14px; border-left: 5px solid #f59e0b; padding-left: 10px; }
        .meta { color: #374151; font-size: 12px; }
        .label { color: #075985; font-weight: bold; }
        .people { margin: 8px 0 0; padding: 0; }
        .people li { list-style: none; margin: 0 0 5px; padding: 6px 8px; background: #eff6ff; color: #1e3a8a; font-weight: bold; border-radius: 4px; }
        .absence-name { color: #7c2d12; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>{{ $order->titolo }}</h1>
    <p><strong>Data servizio:</strong> {{ $order->data_servizio?->format('d/m/Y') }} | <strong>Stato:</strong> {{ \App\Models\WorkOrder::STATI[$order->stato] ?? $order->stato }}</p>

    @if ($order->note)
        <p><strong>Note generali:</strong> {{ $order->note }}</p>
    @endif

    <h2>Cantieri di lavoro</h2>
    @forelse ($order->sites as $site)
        <div class="site">
            <h3>{{ $site->site?->nome }}</h3>
            <table>
                <tbody>
                    <tr>
                        <th style="width: 18%;">Luogo</th>
                        <td>{{ $site->site?->luogo }}</td>
                        <th style="width: 18%;">Orario</th>
                        <td>
                            @if ($site->orario_inizio || $site->orario_fine)
                                {{ $site->orario_inizio ? substr((string) $site->orario_inizio, 0, 5) : '--:--' }}
                                @if ($site->orario_fine)
                                    - {{ substr((string) $site->orario_fine, 0, 5) }}
                                @endif
                            @else
                                <span class="muted">Non indicato</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Mezzo</th>
                        <td colspan="3">
                            @if ($site->vehicle)
                                {{ $site->vehicle->descrizione }} - {{ \App\Models\WorkVehicle::TIPI[$site->vehicle->tipo] ?? $site->vehicle->tipo }}
                            @else
                                <span class="muted">Non indicato</span>
                            @endif
                        </td>
                    </tr>
                    @if ($site->note)
                        <tr>
                            <th>Note</th>
                            <td colspan="3">{{ $site->note }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <p class="label">Persone assegnate</p>
            @if ($site->assignedSocios()->isNotEmpty())
                <ul class="people">
                    @foreach ($site->assignedSocios() as $socio)
                        <li>{{ $socio->nome_completo }}</li>
                    @endforeach
                </ul>
            @else
                <p class="muted">Nessuna persona assegnata.</p>
            @endif
        </div>
    @empty
        <p class="muted">Nessun cantiere inserito.</p>
    @endforelse

    <h2>Assenze</h2>
    <table>
        <thead>
            <tr>
                <th>Socio</th>
                <th>Tipo</th>
                <th>Periodo</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->absences as $absence)
                <tr>
                    <td>
                        @foreach (\App\Models\Socio::query()->whereIn('id', $absence->socio_ids ?? [])->orderBy('cognome')->orderBy('nome')->get() as $socio)
                            <div class="absence-name">{{ $socio->nome_completo }}</div>
                        @endforeach
                    </td>
                    <td>{{ \App\Models\WorkAbsence::TIPI[$absence->tipo] ?? $absence->tipo }}</td>
                    <td>{{ $absence->data_inizio?->format('d/m/Y') }} - {{ $absence->data_fine?->format('d/m/Y') }}</td>
                    <td>{{ $absence->note }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Nessuna assenza inserita.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
