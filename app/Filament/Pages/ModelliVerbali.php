<?php

namespace App\Filament\Pages;

use App\Models\Verbale;
use App\Models\VerbaleTemplate;
use App\Services\VerbaleTemplateService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ModelliVerbali extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Modelli verbali';

    protected static ?string $title = 'Modelli verbali';

    protected static ?string $navigationParentItem = 'Gestione Verbali';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.modelli-verbali';

    public ?array $data = [];

    public function mount(VerbaleTemplateService $templateService): void
    {
        $templateService->ensureDefaults();
        $this->loadTemplate('ammissione');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Editor verbale')
                    ->description('Il contenuto cambia per tipo di verbale. Intestazione e piè di pagina restano quelli generali dei documenti.')
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo verbale')
                            ->options(Verbale::TIPI)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->loadTemplate($state)),
                        RichEditor::make('contenuto')
                            ->label('Contenuto')
                            ->json()
                            ->mergeTags(app(VerbaleTemplateService::class)->mergeTagLabels())
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike'],
                                ['h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['table', 'mergeTags'],
                                ['undo', 'redo'],
                            ])
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Usa il pulsante dei campi dinamici nella toolbar per inserire dati di socio, verbale e variazione.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $tipo = (string) ($state['tipo'] ?? 'ammissione');

        VerbaleTemplate::query()->updateOrCreate(
            ['tipo' => $tipo],
            ['contenuto' => $state['contenuto'] ?? null],
        );

        Notification::make()
            ->title('Modello verbale salvato')
            ->success()
            ->send();
    }

    public function resetTemplate(): void
    {
        $tipo = (string) ($this->data['tipo'] ?? 'ammissione');
        $contenuto = app(VerbaleTemplateService::class)->defaultContent($tipo);

        VerbaleTemplate::query()->updateOrCreate(
            ['tipo' => $tipo],
            ['contenuto' => $contenuto],
        );

        $this->form->fill([
            'tipo' => $tipo,
            'contenuto' => $contenuto,
        ]);

        Notification::make()
            ->title('Modello ripristinato')
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
                            Action::make('resetTemplate')
                                ->label('Ripristina predefinito')
                                ->color('gray')
                                ->requiresConfirmation()
                                ->action('resetTemplate'),
                            Action::make('save')
                                ->label('Salva modello')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function loadTemplate(?string $tipo): void
    {
        $tipo = filled($tipo) ? $tipo : 'ammissione';
        $template = VerbaleTemplate::query()->firstOrCreate(
            ['tipo' => $tipo],
            ['contenuto' => app(VerbaleTemplateService::class)->defaultContent($tipo)],
        );

        $this->form->fill([
            'tipo' => $tipo,
            'contenuto' => $template->contenuto,
        ]);
    }
}
