<?php

namespace App\Filament\Pages;

use App\Models\PayrollDistribution;
use App\Models\PayrollDistributionPage;
use App\Models\Socio;
use App\Services\LocalPayrollOcrService;
use App\Services\PayrollMailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DistribuisciBustePaga extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Distribuisci buste paga';

    protected static ?string $title = 'Distribuisci buste paga';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.distribuisci-buste-paga';

    public ?array $data = ['period' => null, 'file' => null];

    public ?int $distributionId = null;

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
                            ->visible(fn (): bool => $this->distributionId !== null)
                            ->requiresConfirmation()
                            ->modalHeading('Inviare le buste paga?')
                            ->modalDescription('Ogni socio riceverà esclusivamente le pagine a lui associate. L’operazione verrà registrata.')
                            ->action('distribute'),
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

        $storedPath = $file->storeAs('payroll/sources', Str::uuid().'.pdf', 'local');
        $distribution = PayrollDistribution::create([
            'user_id' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'source_path' => $storedPath,
            'period' => trim((string) $this->data['period']),
            'status' => 'processing',
        ]);
        $this->distributionId = $distribution->id;

        try {
            $ocrService->analyze($distribution);
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

    public function setSocio(int $pageId, mixed $socioId): void
    {
        $page = PayrollDistributionPage::query()
            ->where('payroll_distribution_id', $this->distributionId)
            ->findOrFail($pageId);

        $page->update([
            'socio_id' => filled($socioId) ? (int) $socioId : null,
            'match_confidence' => filled($socioId) ? 100 : 0,
            'match_reason' => filled($socioId) ? 'Confermato manualmente' : 'Da associare',
        ]);
    }

    public function distribute(PayrollMailService $mailService): void
    {
        $distribution = $this->currentDistribution();

        if (! $distribution) {
            return;
        }

        try {
            $result = $mailService->distribute($distribution);
            $notification = Notification::make()
                ->title('Distribuzione completata')
                ->body("Inviate: {$result['sent']}. Non riuscite: {$result['failed']}.");
            $result['failed'] > 0 ? $notification->warning() : $notification->success();
            $notification->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Invio bloccato')->body($exception->getMessage())->danger()->send();
        }
    }

    public function currentDistribution(): ?PayrollDistribution
    {
        return $this->distributionId
            ? PayrollDistribution::with(['pages.socio', 'deliveries'])->find($this->distributionId)
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
}
