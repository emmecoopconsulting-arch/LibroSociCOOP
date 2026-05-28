<?php

namespace App\Filament\Resources\Socios;

use App\Filament\Resources\Socios\Pages\CreateSocio;
use App\Filament\Resources\Socios\Pages\EditSocio;
use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Filament\Resources\Socios\Schemas\SocioForm;
use App\Filament\Resources\Socios\Tables\SociosTable;
use App\Models\Socio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SocioResource extends Resource
{
    protected static ?string $model = Socio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'socio';

    protected static ?string $pluralModelLabel = 'soci';

    protected static ?string $navigationLabel = 'Soci';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return SocioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SociosTable::configure($table);
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
            'index' => ListSocios::route('/'),
            'create' => CreateSocio::route('/create'),
            'edit' => EditSocio::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
