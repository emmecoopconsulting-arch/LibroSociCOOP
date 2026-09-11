<?php

namespace App\Filament\Pages;

use App\Filament\Actions\ExportTableExcelAction;
use App\Filament\Resources\SocioMedicalVisitBatches\RelationManagers\VisitsRelationManager;
use App\Filament\Resources\SocioMedicalVisitBatches\SocioMedicalVisitBatchResource;
use App\Filament\Resources\Socios\SocioResource;
use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use App\Models\SocioMedicalVisitBatch;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class VisiteMediche extends Page implements HasTable
{
    use InteractsWithTable;

    public const VISIT_STATUSES = [
        'missing' => 'Nessuna visita',
        'overdue' => 'Scaduta',
        'expiring' => 'In scadenza',
        'valid' => 'In corso di validità',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Visite mediche';

    protected static ?string $title = 'Visite mediche';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.visite-mediche';

    public ?array $data = [];

    public ?array $pdfData = [];

    public function mount(): void
    {
        $this->form->fill([
            'data_visita' => now()->toDateString(),
        ]);

        $this->pdfData = [
            'visits' => [],
        ];
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
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('scadenza', $state ? CarbonImmutable::parse($state)->addYearNoOverflow()->toDateString() : null))
                            ->required(),
                        DatePicker::make('scadenza')
                            ->label('Scadenza')
                            ->helperText('Indica la scadenza riportata sul certificato. Se vuota, viene proposta a un anno dalla visita.')
                            ->afterOrEqual('data_visita'),
                        Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function pdfForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Allega PDF visite mediche')
                    ->visible(fn (): bool => filled($this->pdfData['visits'] ?? []))
                    ->schema([
                        Repeater::make('visits')
                            ->label('Soci registrati')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                Hidden::make('visit_id'),
                                TextInput::make('socio_label')
                                    ->label('Socio')
                                    ->disabled()
                                    ->dehydrated(false),
                                FileUpload::make('pdf_path')
                                    ->label('PDF visita medica')
                                    ->disk('local')
                                    ->directory('visite-mediche')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->maxSize(15360)
                                    ->downloadable()
                                    ->getDownloadableFileUrlUsing(fn (string $file): string => route('visite-mediche.file', [
                                        'path' => Crypt::encryptString($file),
                                        'download' => true,
                                    ]))
                                    ->openable()
                                    ->getOpenableFileUrlUsing(fn (string $file): string => route('visite-mediche.file', [
                                        'path' => Crypt::encryptString($file),
                                    ])),
                            ]),
                    ]),
            ])
            ->statePath('pdfData');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $dataVisita = CarbonImmutable::parse($data['data_visita']);
        $visits = DB::transaction(function () use ($data, $dataVisita): array {
            $batch = SocioMedicalVisitBatch::create([
                'data_visita' => $dataVisita,
                'note' => $data['note'] ?? null,
            ]);
            $visits = [];

            foreach ($data['socio_ids'] as $socioId) {
                $visit = SocioMedicalVisit::create([
                    'batch_id' => $batch->id,
                    'socio_id' => $socioId,
                    'data_visita' => $dataVisita,
                    'scadenza' => filled($data['scadenza'] ?? null) ? CarbonImmutable::parse($data['scadenza']) : $dataVisita->addYearNoOverflow(),
                    'note' => $data['note'] ?? null,
                ]);

                $visit->load('socio');

                $visits[] = [
                    'visit_id' => $visit->id,
                    'socio_label' => $visit->socio?->nome_completo,
                    'pdf_path' => null,
                ];
            }

            return $visits;
        });

        $this->form->fill([
            'data_visita' => now()->toDateString(),
            'scadenza' => null,
            'socio_ids' => [],
            'note' => null,
        ]);

        $this->pdfData = [
            'visits' => $visits,
        ];

        Notification::make()
            ->title('Visite mediche registrate')
            ->body('Ora puoi allegare il PDF per ogni socio registrato.')
            ->success()
            ->send();
    }

    public function savePdfs(): void
    {
        $data = $this->pdfForm->getState();
        $saved = 0;

        foreach ($data['visits'] ?? [] as $visitData) {
            $pdfPath = $this->singlePdfPath($visitData['pdf_path'] ?? null);

            if (blank($visitData['visit_id'] ?? null) || blank($pdfPath)) {
                continue;
            }

            $visit = SocioMedicalVisit::find($visitData['visit_id']);
            if ($visit) {
                $visit->update(['pdf_path' => $pdfPath]);
                $saved++;
            }
        }

        $this->pdfForm->fill([
            'visits' => [],
        ]);

        Notification::make()
            ->title('PDF visite mediche salvati')
            ->body("Allegati caricati: {$saved}")
            ->success()
            ->send();
    }

    private function singlePdfPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path);
        }

        $path = trim((string) $path);

        return $path === '' ? null : $path;
    }

    public function alertDays(): int
    {
        return once(fn (): int => AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS));
    }

    public function selectOverviewFilter(string $status): void
    {
        abort_unless(isset(self::VISIT_STATUSES[$status]) || $status === 'without_pdf', 404);
        $this->tableFilters = [
            'stato' => ['value' => 'attivo'],
            'tipologia' => ['value' => 'ordinario'],
            'visit_status' => ['value' => $status === 'without_pdf' ? null : $status],
            'without_pdf' => ['isActive' => $status === 'without_pdf'],
        ];
        $this->resetTableSearch();
        $this->updatedTableFilters();
    }

    public function visitStatus(Socio $socio): string
    {
        $visit = $socio->latestMedicalVisit;
        if (! $visit) {
            return 'missing';
        }
        if ($visit->scadenza->lt(today())) {
            return 'overdue';
        }

        return $visit->scadenza->lte(today()->addDays($this->alertDays())) ? 'expiring' : 'valid';
    }

    public function filterVisitStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'missing' => $query->doesntHave('medicalVisits'),
            'overdue' => $query->whereHas('latestMedicalVisit', fn (Builder $visit) => $visit->whereDate('scadenza', '<', today())),
            'expiring' => $query->whereHas('latestMedicalVisit', fn (Builder $visit) => $visit->whereDate('scadenza', '>=', today())->whereDate('scadenza', '<=', today()->addDays($this->alertDays()))),
            'valid' => $query->whereHas('latestMedicalVisit', fn (Builder $visit) => $visit->whereDate('scadenza', '>', today()->addDays($this->alertDays()))),
            default => $query,
        };
    }

    public function overview(): array
    {
        $base = Socio::query()->sociEffettivi()->attivi()->ordinari();
        $counts = [];
        foreach (self::VISIT_STATUSES as $status => $label) {
            $counts[$status] = $this->filterVisitStatus(clone $base, $status)->count();
        }
        $counts['without_pdf'] = (clone $base)->whereHas('latestMedicalVisit', fn (Builder $visit) => $visit->where(fn (Builder $pdf) => $pdf->whereNull('pdf_path')->orWhere('pdf_path', '')))->count();

        return $counts;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('history')->label('Storico di tutte le visite')
            ->url(SocioMedicalVisitBatchResource::getUrl())];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Socio::query()->sociEffettivi()->with('latestMedicalVisit'))
            ->heading('Situazione visite per socio')
            ->description('La situazione si riferisce alla visita più recente per data. Usa i filtri per includere altre tipologie o soci non attivi.')
            ->defaultSort('cognome')
            ->headerActions([
                ExportTableExcelAction::make('Situazione visite mediche', 'situazione-visite-mediche'),
            ])
            ->columns([
                TextColumn::make('codice_socio')->label('Codice')->searchable()->sortable(),
                TextColumn::make('nome_completo')->label('Socio')->searchable(['nome', 'cognome']),
                TextColumn::make('mansione')->label('Mansione')->searchable()->toggleable(),
                TextColumn::make('visit_status')->label('Situazione')
                    ->state(fn (Socio $record): string => $this->visitStatus($record))
                    ->formatStateUsing(fn (string $state): string => self::VISIT_STATUSES[$state])
                    ->badge()->color(fn (string $state): string => match ($state) {
                        'missing', 'overdue' => 'danger', 'expiring' => 'warning', default => 'success',
                    }),
                TextColumn::make('latestMedicalVisit.data_visita')->label('Ultima visita')->date('d/m/Y')->placeholder('Mai registrata')->sortable(),
                TextColumn::make('latestMedicalVisit.scadenza')->label('Scadenza')->date('d/m/Y')->sortable(),
                TextColumn::make('remaining_days')->label('Giorni alla scadenza')
                    ->state(function (Socio $record): ?string {
                        if (! $record->latestMedicalVisit) {
                            return null;
                        }
                        $days = (int) today()->diffInDays($record->latestMedicalVisit->scadenza, false);

                        return $days < 0 ? 'Scaduta da '.abs($days).' giorni' : ($days === 0 ? 'Scade oggi' : "Tra {$days} giorni");
                    }),
                IconColumn::make('has_pdf')->label('PDF ultima visita')->boolean()
                    ->state(fn (Socio $record): bool => filled($record->latestMedicalVisit?->pdf_path)),
            ])
            ->filters([
                SelectFilter::make('stato')->label('Stato socio')->options(Socio::STATI)->default('attivo'),
                SelectFilter::make('tipologia')->label('Tipologia socio')
                    ->options(array_diff_key(Socio::TIPOLOGIE, [Socio::TIPOLOGIA_MEMBRO_CDA => true]))->default('ordinario'),
                SelectFilter::make('visit_status')->label('Situazione visita')->options(self::VISIT_STATUSES)
                    ->query(fn (Builder $query, array $data): Builder => $this->filterVisitStatus($query, $data['value'] ?? null)),
                Filter::make('without_pdf')->label('Ultima visita senza PDF')
                    ->query(fn (Builder $query): Builder => $query->whereHas('latestMedicalVisit', fn (Builder $visit) => $visit->where(fn (Builder $pdf) => $pdf->whereNull('pdf_path')->orWhere('pdf_path', '')))),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('registerVisit')->label('Registra visita')->icon('heroicon-o-plus')
                        ->action(function (Socio $record): void {
                            $this->form->fill(['socio_ids' => [$record->id], 'data_visita' => today()->toDateString()]);
                            $this->dispatch('scroll-to-visit-form');
                        })->visible(fn (Socio $record): bool => $record->stato === 'attivo' && $record->tipologia === 'ordinario'),
                    Action::make('editVisit')->label('Gestisci visita / PDF')->icon('heroicon-o-pencil-square')
                        ->visible(fn (Socio $record): bool => $record->latestMedicalVisit !== null)
                        ->schema(VisitsRelationManager::visitFields())
                        ->fillForm(fn (Socio $record): array => $record->latestMedicalVisit->attributesToArray())
                        ->action(function (Socio $record, array $data): void {
                            $record->latestMedicalVisit->update($data);
                            Notification::make()->title('Visita e allegato aggiornati')->success()->send();
                        }),
                    Action::make('downloadPdf')->label('PDF')->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (Socio $record): bool => filled($record->latestMedicalVisit?->pdf_path))
                        ->url(fn (Socio $record): string => route('visite-mediche.download', $record->latestMedicalVisit))
                        ->openUrlInNewTab(),
                    Action::make('socio')->label('Scheda e storico socio')->url(fn (Socio $record): string => SocioResource::getUrl('edit', ['record' => $record])),
                ])->label('Gestisci')->button(),
            ])
            ->emptyStateHeading('Nessun socio corrisponde ai filtri')
            ->paginated([10, 25, 50, 100]);
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
                Form::make([EmbeddedSchema::make('pdfForm')])
                    ->id('pdfForm')
                    ->livewireSubmitHandler('savePdfs')
                    ->footer([
                        Actions::make([
                            Action::make('savePdfs')
                                ->visible(fn (): bool => filled($this->pdfData['visits'] ?? []))
                                ->label('Salva PDF visite')
                                ->submit('savePdfs'),
                        ]),
                    ]),
            ]);
    }
}
