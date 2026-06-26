<?php

namespace App\Filament\Resources\WorkAbsences;

use App\Filament\Resources\WorkAbsences\Pages\CreateWorkAbsence;
use App\Filament\Resources\WorkAbsences\Pages\EditWorkAbsence;
use App\Filament\Resources\WorkAbsences\Pages\ListWorkAbsences;
use App\Filament\Resources\WorkAbsences\Schemas\WorkAbsenceForm;
use App\Filament\Resources\WorkAbsences\Tables\WorkAbsencesTable;
use App\Models\WorkAbsence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkAbsenceResource extends Resource
{
    protected static ?string $model = WorkAbsence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'assenza';

    protected static ?string $pluralModelLabel = 'assenze';

    protected static ?string $navigationLabel = 'Assenze';

    protected static ?string $navigationParentItem = 'Gestione Lavori';

    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return WorkAbsenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkAbsencesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkAbsences::route('/'),
            'create' => CreateWorkAbsence::route('/create'),
            'edit' => EditWorkAbsence::route('/{record}/edit'),
        ];
    }
}
