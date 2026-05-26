<?php

namespace App\Filament\Resources\Comunes;

use App\Filament\Resources\Comunes\Pages\CreateComune;
use App\Filament\Resources\Comunes\Pages\EditComune;
use App\Filament\Resources\Comunes\Pages\ListComunes;
use App\Filament\Resources\Comunes\Schemas\ComuneForm;
use App\Filament\Resources\Comunes\Tables\ComunesTable;
use App\Models\Comune;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ComuneResource extends Resource
{
    protected static ?string $model = Comune::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'comune';

    protected static ?string $pluralModelLabel = 'comuni italiani';

    protected static ?string $navigationLabel = 'Comuni italiani';

    protected static string|UnitEnum|null $navigationGroup = 'Anagrafiche';

    public static function form(Schema $schema): Schema
    {
        return ComuneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComunesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComunes::route('/'),
            'create' => CreateComune::route('/create'),
            'edit' => EditComune::route('/{record}/edit'),
        ];
    }
}
