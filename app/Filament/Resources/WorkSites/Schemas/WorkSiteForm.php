<?php

namespace App\Filament\Resources\WorkSites\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cantiere di lavoro')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Cantiere')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('luogo')
                            ->label('Luogo')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
