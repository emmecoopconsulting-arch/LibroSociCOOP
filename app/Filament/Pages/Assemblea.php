<?php

namespace App\Filament\Pages;

use App\Models\Assemblea as AssembleaModel;
use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\SocioWorkContract;
use App\Services\AssembleaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class Assemblea extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Assemblea';

    protected static ?string $title = 'Assemblea';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.assemblea';

    public ?array $data = [
        'data_assemblea' => null,
        'titolo' => null,
        'note' => null,
        'variations' => [],
    ];

    public function mount(): void
    {
        $today = now()->toDateString();

        $this->data['data_assemblea'] ??= $today;
        $this->data['titolo'] ??= "Assemblea del {$today}";
        $this->data['variations'] = $this->data['variations'] ?: [
            [
                'tipo' => 'proroga_contratto',
                'data_effetto' => $today,
            ],
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati assemblea')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('data_assemblea')
                            ->label('Data assemblea')
                            ->required()
                            ->live(),
                        TextInput::make('titolo')
                            ->label('Titolo verbale')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('Note assemblea')
                            ->columnSpanFull(),
                    ]),
                Section::make('Variazioni deliberate')
                    ->schema([
                        Repeater::make('variations')
                            ->label('Variazioni')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->addActionLabel('Aggiungi variazione')
                            ->columns(2)
                            ->schema([
                                Select::make('socio_id')
                                    ->label('Socio')
                                    ->options(fn (): array => Socio::query()
                                        ->sociEffettivi()
                                        ->orderBy('cognome')
                                        ->orderBy('nome')
                                        ->get()
                                        ->mapWithKeys(fn (Socio $socio): array => [
                                            $socio->id => "{$socio->codice_socio} - {$socio->cognome} {$socio->nome}",
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('tipo')
                                    ->label('Tipo variazione')
                                    ->options(SocioVariation::TIPI)
                                    ->required()
                                    ->live(),
                                DatePicker::make('data_effetto')
                                    ->label('Data effetto')
                                    ->required(),
                                Select::make('tipo_contratto')
                                    ->label('Tipo contratto')
                                    ->options(SocioWorkContract::TIPI_CONTRATTO)
                                    ->required(fn ($get): bool => $get('tipo') === 'variazione_contratto')
                                    ->visible(fn ($get): bool => $get('tipo') === 'variazione_contratto')
                                    ->live(),
                                DatePicker::make('data_inizio')
                                    ->label('Data inizio contratto')
                                    ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto', 'trasformazione_indeterminato'], true)),
                                DatePicker::make('data_fine')
                                    ->label('Data fine contratto')
                                    ->required(fn ($get): bool => $get('tipo') === 'proroga_contratto' || $get('tipo_contratto') === 'determinato')
                                    ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto'], true)),
                                TextInput::make('ore_settimanali')
                                    ->label('Ore settimanali')
                                    ->numeric()
                                    ->step('0.25')
                                    ->minValue(0)
                                    ->maxValue(60)
                                    ->required(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'variazione_ore'], true))
                                    ->visible(fn ($get): bool => in_array($get('tipo'), ['variazione_contratto', 'proroga_contratto', 'trasformazione_indeterminato', 'variazione_ore'], true)),
                                Textarea::make('note')
                                    ->label('Note variazione')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('createAssemblea')
                    ->footer([
                        Actions::make([
                            Action::make('createAssemblea')
                                ->label('Inizia assemblea e genera verbali')
                                ->icon('heroicon-o-document-check')
                                ->action('createAssemblea'),
                        ]),
                    ]),
            ]);
    }

    public function createAssemblea(): void
    {
        try {
            $data = $this->form->getState();
            app(AssembleaService::class)->createWithVariations($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Assemblea non generata')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->data['variations'] = [
            [
                'tipo' => 'proroga_contratto',
                'data_effetto' => now()->toDateString(),
            ],
        ];
        $this->form->fill([
            ...$this->data,
            'data_assemblea' => $this->data['data_assemblea'] ?? now()->toDateString(),
        ]);

        Notification::make()
            ->title('Assemblea generata')
            ->body('Verbale assemblea e verbali singoli generati.')
            ->success()
            ->send();
    }

    public function recentAssemblee()
    {
        return AssembleaModel::query()
            ->withCount('variations')
            ->latest('data_assemblea')
            ->latest('id')
            ->limit(10)
            ->get();
    }
}
