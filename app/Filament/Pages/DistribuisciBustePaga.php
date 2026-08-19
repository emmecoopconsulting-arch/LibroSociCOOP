<?php

namespace App\Filament\Pages;

use App\Models\PayrollDistribution;
use App\Models\PayrollDistributionPage;
use App\Models\Socio;
use App\Services\LocalPayrollOcrService;
use App\Services\PayrollDeliveryReceiptService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollMailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistribuisciBustePaga extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Distribuisci buste paga';

    protected static ?string $title = 'Distribuisci buste paga';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.distribuisci-buste-paga';

    public ?array $data = ['period' => null, 'file' => null, 'additional_message' => null];

    public ?int $distributionId = null;

    /** @var array<int, int|string|null> */
    public array $assignments = [];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nuova distribuzione')
                    ->description('Carica un PDF unico. Ogni pagina viene letta localmente e proposta in associazione a un socio.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('period')
                            ->label('Periodo busta paga')
                            ->placeholder('Giugno 2026')
                            ->required()
                            ->maxLength(100),
                        FileUpload::make('file')
                            ->label('PDF con tutte le buste paga')
                            ->acceptedFileTypes(['application/pdf'])
                            ->storeFiles(false)
                            ->required(),
                        Textarea::make('additional_message')
                            ->label('Messaggio aggiuntivo nelle email')
                            ->helperText('Opzionale. Lo stesso testo verrà inserito nell’email di ogni socio.')
                            ->rows(4)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('analyze')
                ->footer([
                    Actions::make([
                        Action::make('analyze')
                            ->label('Avvia OCR locale')
                            ->icon('heroicon-o-document-magnifying-glass')
                            ->submit('analyze'),
                        Action::make('distribute')
                            ->label('Conferma e invia')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('success')
                            ->visible(fn (): bool => $this->distributionId !== null
                                && ! in_array($this->currentDistribution()?->status, ['sent', 'partial'], true))
                            ->disabled(fn (): bool => $this->hasUnsavedAssignments())
                            ->requiresConfirmation()
                            ->modalHeading('Inviare le buste paga?')
                            ->modalDescription('Ogni socio riceverà esclusivamente le pagine a lui associate. L’operazione verrà registrata.')
                            ->action('distribute'),
                        Action::make('retryFailed')
                            ->label('Riprova fallite')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn (): bool => ($this->currentDistribution()?->failed_count ?? 0) > 0)
                            ->requiresConfirmation()
                            ->modalHeading('Riprovare gli invii falliti?')
                            ->modalDescription('Verranno riprovate soltanto le email in errore. Quelle già inviate non saranno reinviate.')
                            ->action('retryFailed'),
                    ]),
                ]),
        ]);
    }

    public function analyze(LocalPayrollOcrService $ocrService): void
    {
        $this->form->validate();
        $file = $this->uploadedFile();

        if (! $file) {
            Notification::make()->title('Seleziona un file PDF')->danger()->send();

            return;
        }

        $period = Str::slug((string) $this->data['period']) ?: 'periodo-non-indicato';
        $originalName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'buste-paga';
        $storedPath = $file->storeAs(
            'payroll/sources/'.now()->format('Y'),
            now()->format('Ymd-His')."-{$period}-{$originalName}-".Str::lower(Str::random(8)).'.pdf',
            'local',
        );
        $distribution = PayrollDistribution::create([
            'user_id' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'source_path' => $storedPath,
            'period' => trim((string) $this->data['period']),
            'additional_message' => filled($this->data['additional_message'] ?? null)
                ? trim((string) $this->data['additional_message'])
                : null,
            'status' => 'processing',
        ]);
        $this->distributionId = $distribution->id;

        try {
            $ocrService->analyze($distribution);
            app(PayrollDocumentService::class)->sync($distribution->fresh());
            $this->loadAssignments();
            Notification::make()
                ->title('OCR completato')
                ->body("Analizzate {$distribution->fresh()->total_pages} pagine. Controlla tutte le associazioni prima dell’invio.")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);
            $distribution->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            Notification::make()->title('OCR non riuscito')->body($exception->getMessage())->danger()->send();
        }
    }

    public function saveAssignments(PayrollDocumentService $documentService): void
    {
        $distribution = $this->currentDistribution();

        if (! $distribution) {
            return;
        }

        if ($distribution->deliveries->contains('status', 'sent')) {
            Notification::make()
                ->title('Assegnazioni bloccate')
                ->body('Almeno una busta è già stata inviata. Le associazioni non possono più essere modificate.')
                ->danger()
                ->send();

            return;
        }

        $pages = $distribution->pages;
        $selectedSocioIds = collect($this->assignments)
            ->filter(fn (mixed $socioId): bool => filled($socioId))
            ->map(fn (mixed $socioId): int => (int) $socioId)
            ->unique();
        $validSocioIds = Socio::query()
            ->whereKey($selectedSocioIds)
            ->pluck('id');

        if ($validSocioIds->count() !== $selectedSocioIds->count()) {
            Notification::make()
                ->title('Assegnazioni non valide')
                ->body('Uno dei soci selezionati non è più disponibile. Ricarica la pratica e riprova.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($pages): void {
            foreach ($pages as $page) {
                $socioId = $this->assignments[$page->id] ?? null;
                $socioId = filled($socioId) ? (int) $socioId : null;

                if ($page->socio_id === $socioId) {
                    continue;
                }

                $page->update([
                    'socio_id' => $socioId,
                    'match_confidence' => $socioId ? 100 : 0,
                    'match_reason' => $socioId ? 'Confermato manualmente' : 'Da associare',
                ]);
            }
        });

        $documentService->sync($distribution->fresh());
        $this->loadAssignments();

        $remaining = $distribution->fresh()->pages()->whereNull('socio_id')->count();
        Notification::make()
            ->title('Assegnazioni salvate')
            ->body($remaining > 0
                ? "Restano {$remaining} pagine da associare prima dell’invio."
                : 'Tutte le pagine sono associate. Ora puoi confermare l’invio.')
            ->success()
            ->send();
    }

    public function distribute(PayrollMailService $mailService): void
    {
        $distribution = $this->currentDistribution();

        if (! $distribution) {
            return;
        }

        if ($this->hasUnsavedAssignments()) {
            Notification::make()
                ->title('Salva prima le assegnazioni')
                ->body('Hai modifiche non salvate. Usa “Salva assegnazioni” prima di procedere con l’invio.')
                ->warning()
                ->send();

            return;
        }

        try {
            $result = $mailService->distribute($distribution);
            $notification = Notification::make()
                ->title('Distribuzione completata')
                ->body("Inviate: {$result['sent']}. Senza email: {$result['skipped']}. Non riuscite: {$result['failed']}.");
            $result['failed'] > 0 ? $notification->warning() : $notification->success();
            $notification->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Invio bloccato')->body($exception->getMessage())->danger()->send();
        }
    }

    public function retryFailed(PayrollMailService $mailService): void
    {
        $distribution = $this->currentDistribution();

        if (! $distribution || $distribution->failed_count === 0) {
            return;
        }

        try {
            $result = $mailService->distribute($distribution, onlyFailed: true);
            $notification = Notification::make()
                ->title('Nuovo tentativo completato')
                ->body("Inviate totali: {$result['sent']}. Ancora in errore: {$result['failed']}.");
            $result['failed'] > 0 ? $notification->warning() : $notification->success();
            $notification->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Nuovo tentativo non riuscito')->body($exception->getMessage())->danger()->send();
        }
    }

    public function downloadWithoutEmail(PayrollDocumentService $documentService): ?StreamedResponse
    {
        $distribution = $this->currentDistribution();

        if (! $distribution) {
            return null;
        }

        $path = $documentService->combinedWithoutEmail($distribution);

        if (! $path) {
            Notification::make()
                ->title('Nessuna busta senza email')
                ->body('Tutti i soci assegnati hanno un indirizzo email.')
                ->warning()
                ->send();

            return null;
        }

        $period = Str::slug($distribution->period ?: "distribuzione-{$distribution->id}");

        return Storage::disk('local')->download($path, "buste-senza-email-{$period}.pdf");
    }

    public function downloadReceipt(PayrollDeliveryReceiptService $receiptService, ?int $distributionId = null): ?StreamedResponse
    {
        $distribution = PayrollDistribution::with(['deliveries.socio', 'user'])
            ->find($distributionId ?? $this->distributionId);

        if (! $distribution || $distribution->deliveries->isEmpty()) {
            Notification::make()
                ->title('Riepilogo non disponibile')
                ->body('Per questa pratica non risultano ancora tentativi di invio.')
                ->warning()
                ->send();

            return null;
        }

        $filename = 'riepilogo-invio-buste-'.(Str::slug($distribution->period) ?: $distribution->id).'.pdf';
        $pdf = $receiptService->output($distribution);

        return response()->streamDownload(fn () => print $pdf, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function currentDistribution(): ?PayrollDistribution
    {
        return $this->distributionId
            ? PayrollDistribution::with(['pages.socio', 'deliveries.socio', 'documents'])->find($this->distributionId)
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function socioOptions(): array
    {
        return Socio::query()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Socio $socio): array => [
                $socio->id => "{$socio->cognome} {$socio->nome}".(filled($socio->email) ? " — {$socio->email}" : ' — email mancante'),
            ])
            ->all();
    }

    /**
     * @return Collection<int, PayrollDistribution>
     */
    public function distributions()
    {
        return PayrollDistribution::query()
            ->withCount([
                'pages',
                'pages as assigned_pages_count' => fn ($query) => $query->whereNotNull('socio_id'),
            ])
            ->latest()
            ->limit(50)
            ->get();
    }

    public function selectDistribution(int $distributionId): void
    {
        $this->distributionId = PayrollDistribution::query()->findOrFail($distributionId)->id;
        $this->loadAssignments();
    }

    public function hasUnsavedAssignments(): bool
    {
        if (! $this->distributionId) {
            return false;
        }

        $saved = PayrollDistributionPage::query()
            ->where('payroll_distribution_id', $this->distributionId)
            ->pluck('socio_id', 'id')
            ->map(fn (mixed $socioId): ?int => filled($socioId) ? (int) $socioId : null)
            ->all();
        $pending = collect(array_keys($saved))
            ->mapWithKeys(function (int|string $pageId): array {
                $socioId = $this->assignments[(int) $pageId] ?? null;

                return [(int) $pageId => filled($socioId) ? (int) $socioId : null];
            })
            ->all();

        return $saved !== $pending;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('amministratore') ?? false;
    }

    private function uploadedFile(): ?TemporaryUploadedFile
    {
        $file = $this->data['file'] ?? null;

        if ($file instanceof TemporaryUploadedFile) {
            return $file;
        }

        if (is_array($file)) {
            $file = collect($file)->first(fn ($item): bool => $item instanceof TemporaryUploadedFile);
        }

        return $file instanceof TemporaryUploadedFile ? $file : null;
    }

    private function loadAssignments(): void
    {
        $this->assignments = $this->distributionId
            ? PayrollDistributionPage::query()
                ->where('payroll_distribution_id', $this->distributionId)
                ->pluck('socio_id', 'id')
                ->map(fn (mixed $socioId): ?int => filled($socioId) ? (int) $socioId : null)
                ->all()
            : [];
    }
}
