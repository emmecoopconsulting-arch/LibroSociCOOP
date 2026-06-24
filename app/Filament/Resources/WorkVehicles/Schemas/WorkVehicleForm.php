<?php

namespace App\Filament\Resources\WorkVehicles\Schemas;

use App\Models\WorkVehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkVehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mezzo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome mezzo')
                            ->required()
                            ->maxLength(255),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(WorkVehicle::TIPI)
                            ->required()
                            ->default('ditta'),
                        TextInput::make('targa')
                            ->label('Targa')
                            ->maxLength(255),
                        Toggle::make('attivo')
                            ->label('Attivo')
                            ->default(true),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
