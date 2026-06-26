<?php

namespace App\Filament\Resources\WorkVehicles;

use App\Filament\Resources\WorkVehicles\Pages\CreateWorkVehicle;
use App\Filament\Resources\WorkVehicles\Pages\EditWorkVehicle;
use App\Filament\Resources\WorkVehicles\Pages\ListWorkVehicles;
use App\Filament\Resources\WorkVehicles\Schemas\WorkVehicleForm;
use App\Filament\Resources\WorkVehicles\Tables\WorkVehiclesTable;
use App\Models\WorkVehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkVehicleResource extends Resource
{
    protected static ?string $model = WorkVehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'mezzo utilizzato';

    protected static ?string $pluralModelLabel = 'mezzi utilizzati';

    protected static ?string $navigationLabel = 'Mezzi utilizzati';

    protected static ?string $navigationParentItem = 'Gestione Lavori';

    protected static ?int $navigationSort = 33;

    public static function form(Schema $schema): Schema
    {
        return WorkVehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkVehiclesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkVehicles::route('/'),
            'create' => CreateWorkVehicle::route('/create'),
            'edit' => EditWorkVehicle::route('/{record}/edit'),
        ];
    }
}
