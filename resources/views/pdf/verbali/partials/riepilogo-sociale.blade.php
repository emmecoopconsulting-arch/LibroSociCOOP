@php
    $formatEuro = fn (float|int $amount): string => number_format((float) $amount, 2, ',', '.') . ' EUR';
@endphp

<table class="social-summary">
    <tbody>
        <tr>
            <th>Numero di soci ordinari prima dell'ammissione</th>
            <td>{{ $riepilogoSociale['soci_ordinari_prima'] }}</td>
        </tr>
        <tr>
            <th>Capitale sociale complessivo prima dell'ammissione</th>
            <td>{{ $formatEuro($riepilogoSociale['capitale_sociale_prima']) }}</td>
        </tr>
        <tr class="social-summary-spacer">
            <td colspan="2">&nbsp;</td>
        </tr>
        <tr>
            <th>Soci ordinari entrati</th>
            <td>N° {{ $riepilogoSociale['soci_ordinari_entrati'] }}</td>
        </tr>
        <tr>
            <th>Soci ordinari usciti</th>
            <td>N° {{ $riepilogoSociale['soci_ordinari_usciti'] }}</td>
        </tr>
        <tr>
            <th>Soci ordinari complessivi</th>
            <td>{{ $riepilogoSociale['soci_ordinari_complessivi'] }}</td>
        </tr>
        <tr>
            <th>Capitale sociale entrato</th>
            <td>{{ $formatEuro($riepilogoSociale['capitale_sociale_entrato']) }}</td>
        </tr>
        <tr>
            <th>Capitale sociale uscito</th>
            <td>{{ $formatEuro($riepilogoSociale['capitale_sociale_uscito']) }}</td>
        </tr>
        <tr>
            <th>Capitale sociale complessivo</th>
            <td>{{ $formatEuro($riepilogoSociale['capitale_sociale_complessivo']) }}</td>
        </tr>
    </tbody>
</table>
