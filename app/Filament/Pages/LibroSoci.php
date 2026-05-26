<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class LibroSoci extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Libro soci';

    protected static ?string $title = 'Libro soci';

    protected static string|UnitEnum|null $navigationGroup = 'Libro soci';

    protected string $view = 'filament.pages.libro-soci';

    public function soci()
    {
        return Socio::query()
            ->attivi()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();
    }
}
