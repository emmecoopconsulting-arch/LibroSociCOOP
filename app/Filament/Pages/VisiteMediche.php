<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class VisiteMediche extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Visite mediche';

    protected static ?string $title = 'Visite mediche';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.visite-mediche';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'data_visita' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nuova visita medica')
                    ->columns(2)
                    ->schema([
                        Select::make('socio_ids')
                            ->label('Soci ordinari')
                            ->options(fn (): array => Socio::query()
                                ->attivi()
                                ->where('tipologia', 'ordinario')
                                ->orderBy('cognome')
                                ->orderBy('nome')
                                ->get()
                                ->mapWithKeys(fn (Socio $socio): array => [$socio->id => $socio->nome_completo])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('data_visita')
                            ->label('Data visita')
                            ->required(),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $dataVisita = CarbonImmutable::parse($data['data_visita']);

        foreach ($data['socio_ids'] as $socioId) {
            SocioMedicalVisit::create([
                'socio_id' => $socioId,
                'data_visita' => $dataVisita,
                'scadenza' => $dataVisita->addYear(),
                'note' => $data['note'] ?? null,
            ]);
        }

        $this->form->fill([
            'data_visita' => now()->toDateString(),
            'socio_ids' => [],
            'note' => null,
        ]);

        Notification::make()
            ->title('Visite mediche registrate')
            ->body('La prossima scadenza e stata impostata a un anno dalla data visita.')
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
                                ->label('Registra visite')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }
}
