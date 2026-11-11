<?php

namespace App\Filament\Pages;

use App\Models\Assemblea as AssembleaModel;
use App\Models\AssembleaPresenza;
use App\Models\AssembleaPuntoOdg;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class Assemblea extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Assemblea';

    protected static ?string $title = 'Assemblea digitale';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.assemblea';

    public ?int $currentAssembleaId = null;

    public ?array $data = [];

    public function mount(): void
    {
        $active = AssembleaModel::query()
            ->with(['presenze.socio', 'puntiOdg', 'cdaVariations.socio'])
            ->where('stato', 'in_corso')
            ->latest('started_at')
            ->latest('id')
            ->first();

        if ($active) {
            $this->currentAssembleaId = $active->id;
            $this->data = $this->stateFromAssemblea($active);
        } else {
            $this->data = $this->defaultState();
        }

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati assemblea')
                    ->columns(3)
                    ->schema([
                        Select::make('tipo_assemblea')
                            ->label('Tipo assemblea')
                            ->options(AssembleaModel::TIPI_ASSEMBLEA)
                            ->default('soci')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state === 'cda') {
                                    $set('decisioni_variazioni_cda', app(AssembleaService::class)->pendingCdaVariations());
                                    $set('punti_odg', [[
                                        'titolo' => 'Esame e ratifica delle variazioni dei soci',
                                        'descrizione' => 'Il CDA prende atto delle variazioni effettuate e delibera sulla ratifica dei verbali individuali riportati nel riepilogo.',
                                        'discussione' => null,
                                        'esito' => 'da_discutere',
                                    ]]);
                                }
                            }),
                        DatePicker::make('data_assemblea')
                            ->label('Data assemblea')
                            ->required()
                            ->live(),
                        TextInput::make('titolo')
                            ->label('Titolo verbale')
                            ->required()
                            ->maxLength(255),
                        Select::make('modalita')
                            ->label('Modalita')
                            ->options(AssembleaModel::MODALITA)
                            ->required(),
                        TextInput::make('luogo')
                            ->label('Luogo')
                            ->maxLength(255),
                        TextInput::make('presidente')
                            ->label('Presidente')
                            ->helperText('Compilato dal socio con carica Presidente, se presente in anagrafica.')
                            ->maxLength(255),
                        TextInput::make('segretario')
                            ->label('Segretario')
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('Note iniziali')
                            ->columnSpanFull(),
                    ]),
                Section::make(fn ($get): string => $get('tipo_assemblea') === 'cda' ? 'Appello CDA' : 'Appello soci')
                    ->schema([
                        Select::make('present_socio_ids')
                            ->label('Presenti')
                            ->options(fn ($get): array => $this->socioOptions(activeOnly: true, cdaOnly: $get('tipo_assemblea') === 'cda'))
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Repeater::make('deleghe')
                            ->visible(fn ($get): bool => $get('tipo_assemblea') !== 'cda')
                            ->label('Deleghe')
                            ->addActionLabel('Aggiungi delega')
                            ->columns(2)
                            ->schema([
                                Select::make('socio_id')
                                    ->label('Socio delegante')
                                    ->options(fn (): array => $this->socioOptions(activeOnly: true))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('delegato_nome')
                                    ->label('Delegato')
                                    ->maxLength(255),
                            ]),
                    ]),
                Section::make('Ordine del giorno')
                    ->schema([
                        Repeater::make('punti_odg')
                            ->label('Punti')
                            ->hiddenLabel()
                            ->addActionLabel('Aggiungi punto ODG')
                            ->columns(3)
                            ->schema([
                                TextInput::make('titolo')
                                    ->label('Titolo')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Select::make('esito')
                                    ->label('Esito')
                                    ->options(AssembleaPuntoOdg::ESITI)
                                    ->required(),
                                Textarea::make('descrizione')
                                    ->label('Descrizione')
                                    ->columnSpanFull(),
                                Textarea::make('discussione')
                                    ->label('Discussione')
                                    ->columnSpanFull(),
                                TextInput::make('voti_favorevoli')
                                    ->label('Favorevoli')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('voti_contrari')
                                    ->label('Contrari')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('astenuti')
                                    ->label('Astenuti')
                                    ->numeric()
                                    ->minValue(0),
                            ]),
                    ]),
                Section::make('Variazioni da sottoporre al CDA')
                    ->description('Il CDA esamina le variazioni già verbalizzate e delibera separatamente su ciascuna. Le variazioni restano sospese fino all’approvazione.')
                    ->visible(fn ($get): bool => $get('tipo_assemblea') === 'cda')
                    ->schema([
                        Repeater::make('decisioni_variazioni_cda')
                            ->label('Variazioni pendenti')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                TextInput::make('label')
                                    ->label('Verbale / socio')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),
                                Select::make('decisione_cda')
                                    ->label('Decisione')
                                    ->options(SocioVariation::DECISIONI_CDA)
                                    ->placeholder('Da deliberare')
                                    ->columnSpan(1),
                                TextInput::make('socio_variation_id')
                                    ->hidden()
                                    ->dehydrated(),
                            ]),
                    ]),
                Section::make('Delibere soci / variazioni')
                    ->visible(fn ($get): bool => $get('tipo_assemblea') !== 'cda')
                    ->description('Le variazioni vengono applicate solo alla chiusura dell\'assemblea.')
                    ->schema([
                        Repeater::make('variations')
                            ->label('Variazioni')
                            ->hiddenLabel()
                            ->addActionLabel('Aggiungi variazione')
                            ->columns(2)
                            ->schema([
                                Select::make('socio_id')
                                    ->label('Socio')
                                    ->options(fn (): array => $this->socioOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('tipo')
                                    ->label('Tipo variazione')
                                    ->options(SocioVariation::TIPI)
                                    ->helperText(fn ($get): string => $get('tipo') === 'sospensione'
                                        ? 'Documento interno secondo il regolamento del lavoro; non modifica lo status di socio e non passa dal CDA.'
                                        : 'Il verbale individuale resta in attesa della decisione del CDA.')
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
                    ->footer([
                        Actions::make([
                            Action::make('startAssemblea')
                                ->label('Inizia assemblea')
                                ->icon('heroicon-o-play')
                                ->visible(fn (): bool => ! $this->currentAssembleaId)
                                ->action('startAssemblea'),
                            Action::make('saveAssemblea')
                                ->label('Salva avanzamento')
                                ->icon('heroicon-o-check')
                                ->visible(fn (): bool => (bool) $this->currentAssembleaId)
                                ->action('saveAssemblea'),
                            Action::make('closeAssemblea')
                                ->label('Chiudi e genera verbali')
                                ->icon('heroicon-o-document-check')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('Chiudere l\'assemblea?')
                                ->modalDescription('Le variazioni deliberate verranno applicate e verranno generati il verbale assemblea e gli eventuali verbali individuali.')
                                ->visible(fn (): bool => (bool) $this->currentAssembleaId)
                                ->action('closeAssemblea'),
                        ]),
                    ]),
            ]);
    }

    public function startAssemblea(): void
    {
        try {
            $data = $this->form->getState();
            $assemblea = app(AssembleaService::class)->startDigital($data);
            $this->currentAssembleaId = $assemblea->id;
            $this->data = $this->stateFromAssemblea($assemblea->load(['presenze.socio', 'puntiOdg', 'cdaVariations.socio']));
            $this->form->fill($this->data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Assemblea non avviata')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Assemblea avviata')
            ->body('Timestamp di apertura salvato.')
            ->success()
            ->send();
    }

    public function saveAssemblea(): void
    {
        $assemblea = $this->currentAssemblea();

        if (! $assemblea) {
            return;
        }

        try {
            $data = $this->form->getState();
            $assemblea = app(AssembleaService::class)->updateDigital($assemblea, $data);
            $this->data = $this->stateFromAssemblea($assemblea->load(['presenze.socio', 'puntiOdg', 'cdaVariations.socio']));
            $this->form->fill($this->data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Avanzamento non salvato')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Avanzamento salvato')
            ->success()
            ->send();
    }

    public function closeAssemblea(): void
    {
        $assemblea = $this->currentAssemblea();

        if (! $assemblea) {
            return;
        }

        try {
            $data = $this->form->getState();

            if (($data['tipo_assemblea'] ?? 'soci') === 'cda' && collect($data['decisioni_variazioni_cda'] ?? [])
                ->contains(fn (array $row): bool => blank($row['decisione_cda'] ?? null))) {
                throw ValidationException::withMessages([
                    'data.decisioni_variazioni_cda' => 'Indica l’esito per ogni variazione sottoposta al CDA.',
                ]);
            }

            if (blank($data['segretario'] ?? null)) {
                throw ValidationException::withMessages([
                    'data.segretario' => 'Inserisci il nome del segretario prima di chiudere l\'assemblea.',
                ]);
            }

            app(AssembleaService::class)->closeDigital($assemblea, $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Assemblea non chiusa')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->currentAssembleaId = null;
        $this->data = $this->defaultState();
        $this->form->fill($this->data);

        Notification::make()
            ->title('Assemblea chiusa')
            ->body('Verbale assemblea e verbali individuali generati.')
            ->success()
            ->send();
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

        $this->currentAssembleaId = null;
        $this->data = $this->defaultState();
        $this->form->fill($this->data);

        Notification::make()
            ->title('Assemblea generata')
            ->body('Verbale assemblea e verbali singoli generati.')
            ->success()
            ->send();
    }

    public function recentAssemblee()
    {
        return AssembleaModel::query()
            ->withCount(['variations', 'cdaVariations', 'presenze', 'puntiOdg'])
            ->latest('data_assemblea')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function presenzaStats(): array
    {
        $presenti = collect($this->data['present_socio_ids'] ?? [])->filter()->unique()->count();
        $deleghe = collect($this->data['deleghe'] ?? [])
            ->filter(fn (array $delega): bool => filled($delega['socio_id'] ?? null))
            ->pluck('socio_id')
            ->unique()
            ->count();
        $query = Socio::query()->attivi();
        if (($this->data['tipo_assemblea'] ?? 'soci') === 'cda') {
            $query->where('is_cda_member', true);
            $deleghe = 0;
        } else {
            $query->sociEffettivi();
        }
        $totale = $query->count();

        return [
            'presenti' => $presenti,
            'deleghe' => $deleghe,
            'assenti' => max(0, $totale - $presenti - $deleghe),
            'totale' => $totale,
        ];
    }

    private function currentAssemblea(): ?AssembleaModel
    {
        if (! $this->currentAssembleaId) {
            return null;
        }

        return AssembleaModel::query()->find($this->currentAssembleaId);
    }

    private function defaultState(): array
    {
        $today = now()->toDateString();

        return [
            'data_assemblea' => $today,
            'tipo_assemblea' => 'soci',
            'titolo' => "Assemblea del {$today}",
            'modalita' => 'presenza',
            'luogo' => null,
            'presidente' => $this->presidenteLabel(),
            'segretario' => null,
            'note' => null,
            'present_socio_ids' => [],
            'deleghe' => [],
            'punti_odg' => [
                [
                    'titolo' => 'Variazioni soci',
                    'descrizione' => null,
                    'discussione' => null,
                    'esito' => 'da_discutere',
                ],
            ],
            'variations' => [],
            'decisioni_variazioni_cda' => [],
        ];
    }

    private function stateFromAssemblea(AssembleaModel $assemblea): array
    {
        return [
            'data_assemblea' => $assemblea->data_assemblea?->toDateString(),
            'tipo_assemblea' => $assemblea->tipo_assemblea ?: 'soci',
            'titolo' => $assemblea->titolo,
            'modalita' => $assemblea->modalita ?: 'presenza',
            'luogo' => $assemblea->luogo,
            'presidente' => $assemblea->presidente,
            'segretario' => $assemblea->segretario,
            'note' => $assemblea->note,
            'present_socio_ids' => $assemblea->presenze
                ->where('stato', 'presente')
                ->pluck('socio_id')
                ->values()
                ->all(),
            'deleghe' => $assemblea->presenze
                ->where('stato', 'delega')
                ->sortBy(fn (AssembleaPresenza $presenza): string => $presenza->socio?->cognome . $presenza->socio?->nome)
                ->map(fn (AssembleaPresenza $presenza): array => [
                    'socio_id' => $presenza->socio_id,
                    'delegato_nome' => str($presenza->note ?: '')->after('Delega a ')->toString(),
                ])
                ->values()
                ->all(),
            'punti_odg' => $assemblea->puntiOdg
                ->map(fn (AssembleaPuntoOdg $punto): array => [
                    'titolo' => $punto->titolo,
                    'descrizione' => $punto->descrizione,
                    'discussione' => $punto->discussione,
                    'esito' => $punto->esito,
                    'voti_favorevoli' => $punto->voti_favorevoli,
                    'voti_contrari' => $punto->voti_contrari,
                    'astenuti' => $punto->astenuti,
                ])
                ->all(),
            'variations' => [],
            'decisioni_variazioni_cda' => $assemblea->cdaVariations
                ->where('approvazione_cda', 'in_attesa')
                ->map(fn (SocioVariation $variation): array => [
                    'socio_variation_id' => $variation->id,
                    'decisione_cda' => $variation->decisione_cda,
                    'label' => sprintf('%s - %s %s | %s',
                        $variation->socio?->codice_socio,
                        $variation->socio?->cognome,
                        $variation->socio?->nome,
                        SocioVariation::TIPI[$variation->tipo] ?? $variation->tipo,
                    ),
                ])->values()->all(),
        ];
    }

    private function socioOptions(bool $activeOnly = false, bool $cdaOnly = false): array
    {
        $query = Socio::query();

        if (! $cdaOnly) {
            $query->sociEffettivi();
        }

        if ($activeOnly) {
            $query->attivi();
        }
        if ($cdaOnly) {
            $query->where('is_cda_member', true);
        }

        return $query
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Socio $socio): array => [
                $socio->id => "{$socio->codice_socio} - {$socio->cognome} {$socio->nome}",
            ])
            ->all();
    }

    private function presidenteLabel(): ?string
    {
        $presidente = Socio::query()
            ->where('carica_sociale', 'presidente')
            ->attivi()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->first();

        return $presidente ? "{$presidente->cognome} {$presidente->nome}" : null;
    }
}
