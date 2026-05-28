<?php

namespace App\Filament\Pages;

use App\Services\SocioExcelImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportaSoci extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importa soci';

    protected static ?string $title = 'Importa soci da Excel';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.importa-soci';

    public ?array $data = [
        'first_row_contains_headers' => true,
        'update_existing' => false,
        'columns' => [],
        'mapping' => [],
        'sheets' => [],
    ];

    /**
     * @var array<string, string>
     */
    public array $sheets = [];

    /**
     * @var array<string, string>
     */
    public array $columns = [];

    /**
     * @var array{rows: array<int, array<string, mixed>>, total_rows: int, valid_rows: int, invalid_rows: int}|null
     */
    public ?array $preview = null;

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('File')
                        ->schema([
                            Section::make('Carica file')
                                ->description('Sono supportati file Excel .xlsx, .xls e .ods. Il file viene letto per preparare fogli, colonne e anteprima.')
                                ->schema([
                                    FileUpload::make('file')
                                        ->label('File Excel')
                                        ->acceptedFileTypes([
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'application/vnd.ms-excel',
                                            'application/vnd.oasis.opendocument.spreadsheet',
                                        ])
                                        ->storeFiles(false)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn (?TemporaryUploadedFile $state) => $this->loadWorkbook($state)),
                                    Select::make('sheet')
                                        ->label('Foglio')
                                        ->options(fn (): array => $this->availableSheets())
                                        ->required()
                                        ->live()
                                        ->visible(fn (): bool => $this->sheets !== [])
                                        ->afterStateUpdated(fn () => $this->refreshColumnsAndPreview()),
                                    Toggle::make('first_row_contains_headers')
                                        ->label('La prima riga contiene le intestazioni')
                                        ->default(true)
                                        ->live()
                                        ->afterStateUpdated(fn () => $this->refreshColumnsAndPreview()),
                                ]),
                        ]),
                    Step::make('Colonne')
                        ->schema([
                            Section::make('Mappatura colonne')
                                ->description('Associa le colonne del file ai campi anagrafici. Nome, cognome e codice fiscale sono obbligatori.')
                                ->schema([
                                    View::make('filament.pages.partials.importa-soci-mapping')
                                        ->viewData(fn ($livewire): array => [
                                            'livewire' => $livewire,
                                        ]),
                                    Toggle::make('update_existing')
                                        ->label('Aggiorna soci esistenti con lo stesso codice fiscale')
                                        ->helperText('Se disattivato, le righe già presenti vengono saltate.')
                                        ->live()
                                        ->afterStateUpdated(fn () => $this->refreshPreview()),
                                ]),
                        ])
                        ->afterValidation(fn () => $this->refreshPreview()),
                    Step::make('Anteprima')
                        ->schema([
                            Section::make('Controllo righe')
                                ->description('L’anteprima si aggiorna quando cambi file, foglio o mappatura. L’import salta le righe con errori.')
                                ->schema([]),
                        ]),
                ])
                    ->contained(false),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('import')
                    ->footer([
                        Actions::make([
                            Action::make('refreshPreview')
                                ->label('Aggiorna anteprima')
                                ->color('gray')
                                ->icon('heroicon-o-arrow-path')
                                ->action('refreshPreview'),
                            Action::make('import')
                                ->label('Importa righe valide')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->submit('import'),
                        ]),
                    ]),
            ]);
    }

    public function loadWorkbook(?TemporaryUploadedFile $file): void
    {
        $this->sheets = [];
        $this->columns = [];
        $this->preview = null;

        if (! $file) {
            return;
        }

        try {
            $sheets = app(SocioExcelImportService::class)->sheetNames($file->getRealPath());
            $this->sheets = array_combine($sheets, $sheets) ?: [];
            $this->data['sheets'] = $this->sheets;
            $this->data['sheet'] = $sheets[0] ?? null;

            $this->refreshColumnsAndPreview();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('File non leggibile')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshColumnsAndPreview(): void
    {
        $file = $this->uploadedFile();

        if (! $file) {
            return;
        }

        $service = app(SocioExcelImportService::class);
        $sheet = $this->data['sheet'] ?? null;
        $hasHeaders = (bool) ($this->data['first_row_contains_headers'] ?? true);

        $this->columns = $service->columnOptions($file->getRealPath(), $sheet, $hasHeaders);
        $this->data['columns'] = $this->columns;

        if ($hasHeaders) {
            $this->data['mapping'] = $service->guessedMapping($file->getRealPath(), $sheet);
        } else {
            $this->data['mapping'] = array_fill_keys(array_keys(SocioExcelImportService::FIELDS), null);
        }

        $this->form->fill($this->data);
        $this->refreshPreview();
    }

    public function refreshPreview(): void
    {
        $file = $this->uploadedFile();

        if (! $file) {
            $this->preview = null;

            return;
        }

        $this->preview = app(SocioExcelImportService::class)->preview(
            path: $file->getRealPath(),
            mapping: $this->data['mapping'] ?? [],
            sheetName: $this->data['sheet'] ?? null,
            firstRowContainsHeaders: (bool) ($this->data['first_row_contains_headers'] ?? true),
            updateExisting: (bool) ($this->data['update_existing'] ?? false),
        );
    }

    public function import(): void
    {
        $this->form->validate();

        $file = $this->uploadedFile();

        if (! $file) {
            Notification::make()
                ->title('Carica un file Excel prima di importare')
                ->danger()
                ->send();

            return;
        }

        $result = app(SocioExcelImportService::class)->import(
            path: $file->getRealPath(),
            mapping: $this->data['mapping'] ?? [],
            sheetName: $this->data['sheet'] ?? null,
            firstRowContainsHeaders: (bool) ($this->data['first_row_contains_headers'] ?? true),
            updateExisting: (bool) ($this->data['update_existing'] ?? false),
        );

        $this->refreshPreview();

        Notification::make()
            ->title('Import soci completato')
            ->body("Creati: {$result['created']}. Aggiornati: {$result['updated']}. Saltati: {$result['skipped']}.")
            ->success()
            ->send();
    }

    private function uploadedFile(): ?TemporaryUploadedFile
    {
        $file = $this->data['file'] ?? null;

        return $file instanceof TemporaryUploadedFile ? $file : null;
    }

    /**
     * @return array<string, string>
     */
    public function availableSheets(): array
    {
        return $this->sheets ?: ($this->data['sheets'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function availableColumns(): array
    {
        return $this->columns ?: ($this->data['columns'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function importFields(): array
    {
        return SocioExcelImportService::FIELDS;
    }

    public function isRequiredImportField(string $field): bool
    {
        return in_array($field, ['nome', 'cognome', 'codice_fiscale'], true);
    }
}
