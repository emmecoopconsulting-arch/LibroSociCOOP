<?php

namespace App\Filament\Resources\WorkReports;

use App\Filament\Resources\WorkReports\Pages\CreateWorkReport;
use App\Filament\Resources\WorkReports\Pages\EditWorkReport;
use App\Filament\Resources\WorkReports\Pages\ListWorkReports;
use App\Filament\Resources\WorkReports\Schemas\WorkReportForm;
use App\Filament\Resources\WorkReports\Tables\WorkReportsTable;
use App\Models\WorkReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkReportResource extends Resource
{
    protected static ?string $model = WorkReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'rapporto intervento';

    protected static ?string $pluralModelLabel = 'rapporti interventi';

    protected static ?string $navigationLabel = 'Rapporti interventi';

    protected static ?string $navigationParentItem = 'Gestione Lavori';

    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return WorkReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkReports::route('/'),
            'create' => CreateWorkReport::route('/create'),
            'edit' => EditWorkReport::route('/{record}/edit'),
        ];
    }
}
