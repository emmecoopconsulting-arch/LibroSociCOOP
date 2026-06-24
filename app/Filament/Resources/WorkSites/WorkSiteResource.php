<?php

namespace App\Filament\Resources\WorkSites;

use App\Filament\Resources\WorkSites\Pages\CreateWorkSite;
use App\Filament\Resources\WorkSites\Pages\EditWorkSite;
use App\Filament\Resources\WorkSites\Pages\ListWorkSites;
use App\Filament\Resources\WorkSites\Schemas\WorkSiteForm;
use App\Filament\Resources\WorkSites\Tables\WorkSitesTable;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkSiteResource extends Resource
{
    protected static ?string $model = WorkSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $modelLabel = 'cantiere di lavoro';

    protected static ?string $pluralModelLabel = 'cantieri di lavoro';

    protected static ?string $navigationLabel = 'Cantieri di lavoro';

    protected static ?string $navigationParentItem = 'Assegnazione lavoro';

    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return WorkSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkSitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkSites::route('/'),
            'create' => CreateWorkSite::route('/create'),
            'edit' => EditWorkSite::route('/{record}/edit'),
        ];
    }
}
