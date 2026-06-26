<?php

namespace App\Filament\Pages;

use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
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
                            ->required(),
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
                                    ->openable(),
                            ]),
                    ]),
            ])
            ->statePath('pdfData');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $dataVisita = CarbonImmutable::parse($data['data_visita']);
        $visits = [];

        foreach ($data['socio_ids'] as $socioId) {
            $visit = SocioMedicalVisit::create([
                'socio_id' => $socioId,
                'data_visita' => $dataVisita,
                'scadenza' => $dataVisita->addYear(),
                'note' => $data['note'] ?? null,
            ]);

            $visit->load('socio');

            $visits[] = [
                'visit_id' => $visit->id,
                'socio_label' => $visit->socio?->nome_completo,
                'pdf_path' => null,
            ];
        }

        $this->form->fill([
            'data_visita' => now()->toDateString(),
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

            SocioMedicalVisit::query()
                ->find($visitData['visit_id'])
                ?->update(['pdf_path' => $pdfPath]);

            $saved++;
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
                                ->label('Salva PDF visite')
                                ->submit('savePdfs'),
                        ]),
                    ]),
            ]);
    }
}
