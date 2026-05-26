<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 108px 46px 56px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.6; }
        h1 { font-size: 22px; margin-bottom: 28px; text-align: center; }
        h2 { font-size: 15px; margin-top: 28px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; }
        th { width: 34%; text-align: left; font-weight: normal; background: #f9fafb; }
        .footer { margin-top: 80px; display: flex; justify-content: space-between; }
        .document-header { position: fixed; top: -82px; left: 0; right: 0; height: 62px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 50px; max-width: 150px; margin-right: 16px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>{{ $verbale->titolo }}</h1>

    <p>
        In data {{ $verbale->data_verbale?->format('d/m/Y') }} viene verbalizzata la seguente
        variazione per il socio {{ $socio->nome_completo }}, codice socio {{ $socio->codice_socio }}
        e codice fiscale {{ $socio->codice_fiscale }}.
    </p>

    <h2>Dati variazione</h2>
    <table>
        <tr>
            <th>Tipo variazione</th>
            <td>{{ \App\Models\SocioVariation::TIPI[$verbale->tipo] ?? $verbale->tipo }}</td>
        </tr>
        <tr>
            <th>Data effetto</th>
            <td>{{ $variation?->data_effetto?->format('d/m/Y') ?: '-' }}</td>
        </tr>
        @if ($variation?->tipo_contratto)
            <tr>
                <th>Tipo contratto</th>
                <td>{{ \App\Models\SocioWorkContract::TIPI_CONTRATTO[$variation->tipo_contratto] ?? $variation->tipo_contratto }}</td>
            </tr>
        @endif
        @if ($variation?->data_inizio)
            <tr>
                <th>Data inizio contratto</th>
                <td>{{ $variation->data_inizio->format('d/m/Y') }}</td>
            </tr>
        @endif
        @if ($variation?->data_fine)
            <tr>
                <th>Data fine contratto</th>
                <td>{{ $variation->data_fine->format('d/m/Y') }}</td>
            </tr>
        @endif
        @if ($variation?->ore_settimanali)
            <tr>
                <th>Ore settimanali</th>
                <td>{{ number_format((float) $variation->ore_settimanali, 2, ',', '.') }}</td>
            </tr>
        @endif
        @if ($variation?->note)
            <tr>
                <th>Note</th>
                <td>{{ $variation->note }}</td>
            </tr>
        @endif
    </table>

    <div class="footer">
        <div>Il Presidente</div>
        <div>Il Socio</div>
    </div>
</body>
</html>
