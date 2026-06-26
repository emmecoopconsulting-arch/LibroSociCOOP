<?php

namespace App\Filament\Resources\SocioMedicalVisitBatches;

use App\Filament\Resources\SocioMedicalVisitBatches\Pages\EditSocioMedicalVisitBatch;
use App\Filament\Resources\SocioMedicalVisitBatches\Pages\ListSocioMedicalVisitBatches;
use App\Filament\Resources\SocioMedicalVisitBatches\RelationManagers\VisitsRelationManager;
use App\Filament\Resources\SocioMedicalVisitBatches\Schemas\SocioMedicalVisitBatchForm;
use App\Filament\Resources\SocioMedicalVisitBatches\Tables\SocioMedicalVisitBatchesTable;
use App\Models\SocioMedicalVisitBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SocioMedicalVisitBatchResource extends Resource
{
    protected static ?string $model = SocioMedicalVisitBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'registrazione visite mediche';

    protected static ?string $pluralModelLabel = 'storico visite mediche';

    protected static ?string $navigationLabel = 'Storico visite mediche';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return SocioMedicalVisitBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocioMedicalVisitBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VisitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocioMedicalVisitBatches::route('/'),
            'edit' => EditSocioMedicalVisitBatch::route('/{record}/edit'),
        ];
    }
}
