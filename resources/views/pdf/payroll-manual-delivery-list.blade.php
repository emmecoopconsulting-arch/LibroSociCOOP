<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 36px; }
        body { color: #222; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { margin: 0 0 5px; font-size: 19px; }
        .subtitle { margin-bottom: 18px; color: #555; font-size: 11px; }
        .summary {
            margin-bottom: 15px;
            padding: 9px 11px;
            border: 1px solid #ddd;
            background: #f7f7f7;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 7px; border: 1px solid #bbb; vertical-align: middle; }
        th { background: #ececec; font-size: 9px; text-align: left; text-transform: uppercase; }
        .number { width: 24px; text-align: center; }
        .code { width: 82px; }
        .pages { width: 42px; text-align: center; }
        .date { width: 82px; }
        .check { width: 52px; text-align: center; }
        .signature { width: 130px; }
        .box { display: inline-block; width: 12px; height: 12px; border: 1px solid #555; }
        .footer { margin-top: 18px; color: #666; font-size: 8px; }
    </style>
</head>
<body>
    <h1>Elenco consegne manuali buste paga</h1>
    <div class="subtitle">
        Periodo: <strong>{{ $distribution->period ?: 'non indicato' }}</strong>
        &nbsp;·&nbsp; Lavoro #{{ $distribution->id }}
        &nbsp;·&nbsp; Generato il {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="summary">
        Buste da consegnare a mano: <strong>{{ $recipients->count() }}</strong>.
        Le pagine successive contengono i documenti da stampare.
    </div>

    <table>
        <thead>
            <tr>
                <th class="number">N.</th>
                <th>Socio / dipendente</th>
                <th class="code">Codice socio</th>
                <th class="pages">Pagine</th>
                <th class="check">Consegnata</th>
                <th class="date">Data</th>
                <th class="signature">Firma / note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recipients as $index => $recipient)
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td><strong>{{ $recipient['socio']->cognome }} {{ $recipient['socio']->nome }}</strong></td>
                    <td class="code">{{ $recipient['socio']->codice_socio ?: '—' }}</td>
                    <td class="pages">{{ $recipient['pages'] }}</td>
                    <td class="check"><span class="box"></span></td>
                    <td class="date"></td>
                    <td class="signature"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento operativo riservato. Conservare e trattare secondo le procedure interne sulla protezione dei dati personali.
    </div>
</body>
</html>
