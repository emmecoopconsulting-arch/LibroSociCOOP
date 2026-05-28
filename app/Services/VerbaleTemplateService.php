<?php

namespace App\Services;

use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\SocioWorkContract;
use App\Models\Verbale;
use App\Models\VerbaleTemplate;
use DateTimeInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;

class VerbaleTemplateService
{
    /**
     * @return array<string, string>
     */
    public function mergeTagLabels(): array
    {
        return [
            'verbale.titolo' => 'Verbale: titolo',
            'verbale.tipo' => 'Verbale: tipo',
            'verbale.data' => 'Verbale: data',
            'socio.codice' => 'Socio: codice',
            'socio.nome' => 'Socio: nome',
            'socio.cognome' => 'Socio: cognome',
            'socio.nome_completo' => 'Socio: nome completo',
            'socio.codice_fiscale' => 'Socio: codice fiscale',
            'socio.tipologia' => 'Socio: tipologia',
            'socio.data_ammissione' => 'Socio: data ammissione',
            'socio.capitale_versato' => 'Socio: capitale versato',
            'variazione.tipo' => 'Variazione: tipo',
            'variazione.data_effetto' => 'Variazione: data effetto',
            'variazione.tipo_contratto' => 'Variazione: tipo contratto',
            'variazione.data_inizio' => 'Variazione: data inizio contratto',
            'variazione.data_fine' => 'Variazione: data fine contratto',
            'variazione.ore_settimanali' => 'Variazione: ore settimanali',
            'variazione.note' => 'Variazione: note',
            'riepilogo.sociale' => 'Tabella: riepilogo sociale',
            'firme.presidente_socio' => 'Firme: Presidente e Socio',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeTagValues(Verbale $verbale, ?array $riepilogoSociale = null): array
    {
        $verbale->loadMissing(['socio', 'variation']);

        $socio = $verbale->socio;
        $variation = $verbale->variation;

        return [
            'verbale.titolo' => $this->text($verbale->titolo),
            'verbale.tipo' => Verbale::TIPI[$verbale->tipo] ?? SocioVariation::TIPI[$verbale->tipo] ?? $verbale->tipo,
            'verbale.data' => $this->formatDate($verbale->data_verbale),
            'socio.codice' => $this->text($socio?->codice_socio),
            'socio.nome' => $this->text($socio?->nome),
            'socio.cognome' => $this->text($socio?->cognome),
            'socio.nome_completo' => $this->text($socio?->nome_completo),
            'socio.codice_fiscale' => $this->text($socio?->codice_fiscale),
            'socio.tipologia' => $socio ? (Socio::TIPOLOGIE[$socio->tipologia] ?? $socio->tipologia) : '-',
            'socio.data_ammissione' => $this->formatDate($socio?->data_ammissione),
            'socio.capitale_versato' => $this->formatMoney($socio?->capitale_versato),
            'variazione.tipo' => SocioVariation::TIPI[$variation?->tipo ?? $verbale->tipo] ?? $variation?->tipo ?? $verbale->tipo,
            'variazione.data_effetto' => $this->formatDate($variation?->data_effetto),
            'variazione.tipo_contratto' => $variation?->tipo_contratto ? (SocioWorkContract::TIPI_CONTRATTO[$variation->tipo_contratto] ?? $variation->tipo_contratto) : '-',
            'variazione.data_inizio' => $this->formatDate($variation?->data_inizio),
            'variazione.data_fine' => $this->formatDate($variation?->data_fine),
            'variazione.ore_settimanali' => filled($variation?->ore_settimanali) ? number_format((float) $variation->ore_settimanali, 2, ',', '.') : '-',
            'variazione.note' => $this->text($variation?->note),
            'riepilogo.sociale' => $riepilogoSociale ? new HtmlString(view('pdf.verbali.partials.riepilogo-sociale', [
                'riepilogoSociale' => $riepilogoSociale,
            ])->render()) : '-',
            'firme.presidente_socio' => new HtmlString('<div class="signature-footer"><div>Il Presidente</div><div>Il Socio</div></div>'),
        ];
    }

    public function render(Verbale $verbale, ?array $riepilogoSociale = null): string
    {
        $content = VerbaleTemplate::query()
            ->where('tipo', $verbale->tipo)
            ->first()?->contenuto ?: $this->defaultContent($verbale->tipo);

        return RichContentRenderer::make($content)
            ->mergeTags($this->mergeTagValues($verbale, $riepilogoSociale))
            ->toHtml();
    }

    public function ensureDefaults(): void
    {
        foreach (Verbale::TIPI as $tipo => $label) {
            VerbaleTemplate::query()->firstOrCreate(
                ['tipo' => $tipo],
                ['contenuto' => $this->defaultContent($tipo)],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultContent(string $tipo): array
    {
        return RichContentRenderer::make($this->defaultHtml($tipo))->toArray();
    }

    public function defaultHtml(string $tipo): string
    {
        if ($tipo === 'ammissione') {
            return <<<'HTML'
<h1><span data-type="mergeTag" data-id="verbale.titolo"></span></h1>
<p>In data <span data-type="mergeTag" data-id="socio.data_ammissione"></span>, <span data-type="mergeTag" data-id="socio.nome_completo"></span> con codice fiscale <span data-type="mergeTag" data-id="socio.codice_fiscale"></span> viene ammesso in qualità di <span data-type="mergeTag" data-id="socio.tipologia"></span> presso la Cooperativa Sociale a Responsabilità Limitata FTM.</p>
<p>In concomitanza dell'ammissione il Socio ha provveduto a versare <strong><span data-type="mergeTag" data-id="socio.capitale_versato"></span></strong>.</p>
<p><span data-type="mergeTag" data-id="riepilogo.sociale"></span></p>
<p><span data-type="mergeTag" data-id="firme.presidente_socio"></span></p>
HTML;
        }

        return <<<'HTML'
<h1><span data-type="mergeTag" data-id="verbale.titolo"></span></h1>
<p>In data <span data-type="mergeTag" data-id="verbale.data"></span> viene verbalizzata la seguente variazione per il socio <span data-type="mergeTag" data-id="socio.nome_completo"></span>, codice socio <span data-type="mergeTag" data-id="socio.codice"></span> e codice fiscale <span data-type="mergeTag" data-id="socio.codice_fiscale"></span>.</p>
<h2>Dati variazione</h2>
<table>
    <tbody>
        <tr><th>Tipo variazione</th><td><span data-type="mergeTag" data-id="variazione.tipo"></span></td></tr>
        <tr><th>Data effetto</th><td><span data-type="mergeTag" data-id="variazione.data_effetto"></span></td></tr>
        <tr><th>Tipo contratto</th><td><span data-type="mergeTag" data-id="variazione.tipo_contratto"></span></td></tr>
        <tr><th>Data inizio contratto</th><td><span data-type="mergeTag" data-id="variazione.data_inizio"></span></td></tr>
        <tr><th>Data fine contratto</th><td><span data-type="mergeTag" data-id="variazione.data_fine"></span></td></tr>
        <tr><th>Ore settimanali</th><td><span data-type="mergeTag" data-id="variazione.ore_settimanali"></span></td></tr>
        <tr><th>Note</th><td><span data-type="mergeTag" data-id="variazione.note"></span></td></tr>
    </tbody>
</table>
<p><span data-type="mergeTag" data-id="firme.presidente_socio"></span></p>
HTML;
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        return filled($date) ? (string) $date : '-';
    }

    private function formatMoney(mixed $amount): string
    {
        return filled($amount) ? number_format((float) $amount, 2, ',', '.').' EUR' : '-';
    }

    private function text(mixed $value): string
    {
        return filled($value) ? (string) $value : '-';
    }
}
