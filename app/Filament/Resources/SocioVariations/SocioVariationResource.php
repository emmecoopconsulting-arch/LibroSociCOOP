<?php

namespace App\Filament\Resources\SocioVariations;

use App\Filament\Resources\SocioVariations\Pages\CreateSocioVariation;
use App\Filament\Resources\SocioVariations\Pages\ListSocioVariations;
use App\Filament\Resources\SocioVariations\Schemas\SocioVariationForm;
use App\Filament\Resources\SocioVariations\Tables\SocioVariationsTable;
use App\Models\SocioVariation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SocioVariationResource extends Resource
{
    protected static ?string $model = SocioVariation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'variazione socio';

    protected static ?string $pluralModelLabel = 'variazioni soci';

    protected static ?string $navigationLabel = 'Variazioni soci';

    protected static string|UnitEnum|null $navigationGroup = 'Libro soci';

    public static function form(Schema $schema): Schema
    {
        return SocioVariationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocioVariationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocioVariations::route('/'),
            'create' => CreateSocioVariation::route('/create'),
        ];
    }
}
