<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 92px 28px 46px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { font-size: 18px; margin-bottom: 18px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .document-header { position: fixed; top: -70px; left: 0; right: 0; height: 52px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 42px; max-width: 140px; margin-right: 14px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>Libro soci attivo</h1>

    <table>
        <thead>
            <tr>
                <th>Codice socio</th>
                <th>Cognome</th>
                <th>Nome</th>
                <th>Codice fiscale</th>
                <th>Tipologia</th>
                <th>Data ammissione</th>
                <th>Capitale versato</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($soci as $socio)
                <tr>
                    <td>{{ $socio->codice_socio }}</td>
                    <td>{{ $socio->cognome }}</td>
                    <td>{{ $socio->nome }}</td>
                    <td>{{ $socio->codice_fiscale }}</td>
                    <td>{{ \App\Models\Socio::TIPOLOGIE[$socio->tipologia] ?? $socio->tipologia }}</td>
                    <td>{{ $socio->data_ammissione?->format('d/m/Y') }}</td>
                    <td>{{ number_format((float) $socio->capitale_versato, 2, ',', '.') }} EUR</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
