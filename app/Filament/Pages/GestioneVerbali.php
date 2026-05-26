<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\Verbale;
use App\Services\VerbalePdfService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GestioneVerbali extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Gestione Verbali';

    protected static ?string $title = 'Gestione Verbali';

    protected static string|UnitEnum|null $navigationGroup = 'Libro soci';

    protected string $view = 'filament.pages.gestione-verbali';

    public function missingSoci()
    {
        return Socio::query()
            ->attivi()
            ->whereDoesntHave('verbales', fn ($query) => $query
                ->where('tipo', 'ammissione'))
            ->orderBy('data_ammissione')
            ->orderBy('cognome')
            ->limit(50)
            ->get();
    }

    public function missingCount(): int
    {
        return $this->missingAdmissionsCount() + $this->pendingVerbalesCount();
    }

    public function missingAdmissionsCount(): int
    {
        return Socio::query()
            ->attivi()
            ->whereDoesntHave('verbales', fn ($query) => $query
                ->where('tipo', 'ammissione'))
            ->count();
    }

    public function pendingVerbales()
    {
        return Verbale::query()
            ->where('stato', 'da_generare')
            ->with('socio')
            ->orderBy('data_verbale')
            ->orderBy('id')
            ->limit(50)
            ->get();
    }

    public function pendingVerbalesCount(): int
    {
        return Verbale::query()
            ->where('stato', 'da_generare')
            ->count();
    }

    public function generatedCount(): int
    {
        return Verbale::query()
            ->where('stato', 'generato')
            ->count();
    }

    public function generate(int $socioId): void
    {
        $socio = Socio::query()->findOrFail($socioId);
        app(VerbalePdfService::class)->generateAdmission($socio);

        Notification::make()
            ->title('Verbale generato')
            ->success()
            ->send();
    }

    public function generateVerbale(int $verbaleId): void
    {
        $verbale = Verbale::query()->findOrFail($verbaleId);
        app(VerbalePdfService::class)->generate($verbale);

        Notification::make()
            ->title('Verbale generato')
            ->success()
            ->send();
    }

    public function generateAll(): void
    {
        $count = app(VerbalePdfService::class)->generateAllPending();

        Notification::make()
            ->title("Verbali generati: {$count}")
            ->success()
            ->send();
    }

    public function verbaleTipoLabel(string $tipo): string
    {
        return Verbale::TIPI[$tipo] ?? SocioVariation::TIPI[$tipo] ?? $tipo;
    }
}
