<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\MassCommunication;
use App\Models\Socio;
use App\Services\MassCommunicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ComunicazioniMassive extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Comunicazioni massive';

    protected static ?string $title = 'Comunicazioni massive';

    protected static ?string $navigationParentItem = 'Soci';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.comunicazioni-massive';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'subject' => null,
            'body' => null,
            'attachment' => null,
            'attachment_description' => null,
            'statuses' => ['attivo'],
            'types' => [],
            'mansione' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Messaggio')
                ->description('Ogni destinatario riceve una email separata: gli indirizzi degli altri soci non saranno visibili.')
                ->schema([
                    TextInput::make('subject')->label('Oggetto')->required()->maxLength(255),
                    Textarea::make('body')->label('Messaggio')->required()->rows(8)->maxLength(10000),
                    FileUpload::make('attachment')->label('Allegato opzionale')->storeFiles(false)->maxSize(20480),
                    TextInput::make('attachment_description')
                        ->label('Descrizione nel fascicolo del socio')
                        ->helperText('Obbligatoria quando alleghi un documento.')
                        ->maxLength(255),
                ]),
            Section::make('Destinatari')
                ->description('Se non selezioni una tipologia o una mansione, vengono incluse tutte quelle compatibili con gli stati scelti.')
                ->columns(2)
                ->schema([
                    CheckboxList::make('statuses')->label('Stato socio')->options(Socio::STATI)->required()->columns(2),
                    CheckboxList::make('types')
                        ->label('Tipologia')
                        ->options(collect(Socio::TIPOLOGIE)->except(Socio::TIPOLOGIA_MEMBRO_CDA)->all())
                        ->columns(2),
                    Select::make('mansione')->label('Mansione')->options(array_combine(AppSetting::mansioni(), AppSetting::mansioni()))->searchable(),
                ]),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([
                    Actions::make([
                        Action::make('send')
                            ->label('Invia comunicazione')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Confermare l’invio massivo?')
                            ->modalDescription(fn (): string => 'Destinatari individuati: '.$this->recipientCount().'. L’allegato, se presente, verrà copiato nel fascicolo di ciascuno.')
                            ->submit('send'),
                    ]),
                ]),
        ]);
    }

    public function send(MassCommunicationService $service): void
    {
        $data = $this->form->getState();
        $file = $this->uploadedFile();

        if ($file && blank($data['attachment_description'] ?? null)) {
            $this->addError('data.attachment_description', 'Inserisci la descrizione con cui archiviare il documento.');

            return;
        }

        $filters = [
            'statuses' => array_values($data['statuses'] ?? []),
            'types' => array_values($data['types'] ?? []),
            'mansione' => filled($data['mansione'] ?? null) ? $data['mansione'] : null,
        ];
        $count = $service->recipientCount($filters);

        if ($count === 0) {
            Notification::make()->title('Nessun destinatario')->body('Modifica i filtri prima di procedere.')->warning()->send();

            return;
        }

        $path = null;
        if ($file) {
            $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'allegato';
            $extension = Str::lower($file->getClientOriginalExtension());
            $path = $file->storeAs('comunicazioni/allegati/'.now()->format('Y'), now()->format('Ymd-His').'-'.$base.'-'.Str::lower(Str::random(8)).($extension ? ".{$extension}" : ''), 'local');
        }

        $communication = MassCommunication::create([
            'user_id' => auth()->id(),
            'subject' => trim((string) $data['subject']),
            'body' => trim((string) $data['body']),
            'attachment_path' => $path,
            'attachment_name' => $file?->getClientOriginalName(),
            'attachment_description' => filled($data['attachment_description'] ?? null) ? trim((string) $data['attachment_description']) : null,
            'filters' => $filters,
            'recipient_count' => $count,
        ]);

        try {
            $result = $service->send($communication);
            $notification = Notification::make()
                ->title('Comunicazione elaborata')
                ->body("Inviate: {$result['sent']}. Senza email: {$result['skipped']}. Non riuscite: {$result['failed']}.");
            $result['failed'] > 0 ? $notification->warning() : $notification->success();
            $notification->send();
            $this->mount();
        } catch (\Throwable $exception) {
            report($exception);
            $communication->update(['status' => 'failed']);
            Notification::make()->title('Invio bloccato')->body($exception->getMessage())->danger()->send();
        }
    }

    public function recipientCount(): int
    {
        return app(MassCommunicationService::class)->recipientCount([
            'statuses' => $this->data['statuses'] ?? [],
            'types' => $this->data['types'] ?? [],
            'mansione' => $this->data['mansione'] ?? null,
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('amministratore') ?? false;
    }

    private function uploadedFile(): ?TemporaryUploadedFile
    {
        $file = $this->data['attachment'] ?? null;
        if (is_array($file)) {
            $file = collect($file)->first(fn ($item): bool => $item instanceof TemporaryUploadedFile);
        }

        return $file instanceof TemporaryUploadedFile ? $file : null;
    }
}
