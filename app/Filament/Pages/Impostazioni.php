<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Impostazioni extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $title = 'Impostazioni';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.impostazioni';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'permesso_soggiorno_alert_days' => AppSetting::int(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS),
            'visita_medica_alert_days' => AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS),
            'mansioni' => AppSetting::mansioni(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scadenze')
                    ->columns(2)
                    ->schema([
                        TextInput::make('permesso_soggiorno_alert_days')
                            ->label('Alert permesso di soggiorno')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('giorni prima'),
                        TextInput::make('visita_medica_alert_days')
                            ->label('Alert visita medica')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('giorni prima'),
                    ]),
                Section::make('Mansioni')
                    ->schema([
                        TagsInput::make('mansioni')
                            ->label('Mansioni disponibili')
                            ->placeholder('Aggiungi mansione')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::setValue(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS, (int) $data['permesso_soggiorno_alert_days']);
        AppSetting::setValue(AppSetting::VISITA_MEDICA_ALERT_DAYS, (int) $data['visita_medica_alert_days']);
        AppSetting::setValue(AppSetting::MANSIONI, $this->sanitizeMansioni($data['mansioni'] ?? []));

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salva impostazioni')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<int, mixed>  $mansioni
     * @return array<int, string>
     */
    private function sanitizeMansioni(array $mansioni): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $mansione): string => trim((string) $mansione),
            $mansioni,
        ))));
    }
}
