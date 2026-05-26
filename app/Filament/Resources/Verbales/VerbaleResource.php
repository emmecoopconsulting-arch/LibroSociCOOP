<?php

namespace App\Filament\Resources\Verbales;

use App\Filament\Resources\Verbales\Pages\CreateVerbale;
use App\Filament\Resources\Verbales\Pages\EditVerbale;
use App\Filament\Resources\Verbales\Pages\ListVerbales;
use App\Filament\Resources\Verbales\Schemas\VerbaleForm;
use App\Filament\Resources\Verbales\Tables\VerbalesTable;
use App\Models\Verbale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VerbaleResource extends Resource
{
    protected static ?string $model = Verbale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'verbale';

    protected static ?string $pluralModelLabel = 'verbali';

    protected static ?string $navigationLabel = 'Verbali';

    protected static string|UnitEnum|null $navigationGroup = 'Libro soci';

    public static function form(Schema $schema): Schema
    {
        return VerbaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerbalesTable::configure($table);
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
            'index' => ListVerbales::route('/'),
            'create' => CreateVerbale::route('/create'),
            'edit' => EditVerbale::route('/{record}/edit'),
        ];
    }
}
