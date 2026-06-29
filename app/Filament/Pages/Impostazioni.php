<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\LocalPayrollOcrService;
use App\Services\PayrollMailService;
use App\Services\S3ArchiveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Impostazioni extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $title = 'Impostazioni';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.impostazioni';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'permesso_soggiorno_alert_days' => AppSetting::int(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS),
            'visita_medica_alert_days' => AppSetting::int(AppSetting::VISITA_MEDICA_ALERT_DAYS),
            'mansioni' => AppSetting::mansioni(),
            's3_archive_enabled' => AppSetting::bool(AppSetting::S3_ARCHIVE_ENABLED),
            's3_access_key_id' => AppSetting::string(AppSetting::S3_ACCESS_KEY_ID),
            's3_secret_access_key' => null,
            's3_default_region' => AppSetting::string(AppSetting::S3_DEFAULT_REGION),
            's3_bucket' => AppSetting::string(AppSetting::S3_BUCKET),
            's3_endpoint' => AppSetting::string(AppSetting::S3_ENDPOINT),
            's3_use_path_style_endpoint' => AppSetting::bool(AppSetting::S3_USE_PATH_STYLE_ENDPOINT),
            'smtp_host' => AppSetting::string(AppSetting::SMTP_HOST),
            'smtp_port' => AppSetting::int(AppSetting::SMTP_PORT),
            'smtp_scheme' => AppSetting::string(AppSetting::SMTP_SCHEME) ?: 'smtp',
            'smtp_username' => AppSetting::string(AppSetting::SMTP_USERNAME),
            'smtp_password' => null,
            'smtp_from_address' => AppSetting::string(AppSetting::SMTP_FROM_ADDRESS),
            'smtp_from_name' => AppSetting::string(AppSetting::SMTP_FROM_NAME),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scadenze')
                    ->columns(2)
                    ->schema([
                        TextInput::make('permesso_soggiorno_alert_days')
                            ->label('Alert permesso di soggiorno')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('giorni prima'),
                        TextInput::make('visita_medica_alert_days')
                            ->label('Alert visita medica')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('giorni prima'),
                    ]),
                Section::make('Mansioni')
                    ->schema([
                        TagsInput::make('mansioni')
                            ->label('Mansioni disponibili')
                            ->placeholder('Aggiungi mansione')
                            ->required(),
                    ]),
                Section::make('Archiviazione parallela S3')
                    ->columns(2)
                    ->schema([
                        Toggle::make('s3_archive_enabled')
                            ->label('Attiva archiviazione S3')
                            ->live(),
                        TextInput::make('s3_bucket')
                            ->label('Bucket')
                            ->required(fn ($get): bool => (bool) $get('s3_archive_enabled'))
                            ->maxLength(255),
                        TextInput::make('s3_default_region')
                            ->label('Regione')
                            ->required(fn ($get): bool => (bool) $get('s3_archive_enabled'))
                            ->maxLength(255),
                        TextInput::make('s3_access_key_id')
                            ->label('Access key ID')
                            ->required(fn ($get): bool => (bool) $get('s3_archive_enabled'))
                            ->maxLength(255),
                        TextInput::make('s3_secret_access_key')
                            ->label('Secret access key')
                            ->password()
                            ->revealable()
                            ->helperText('Lasciare vuoto per mantenere la chiave gia salvata.')
                            ->required(fn ($get): bool => (bool) $get('s3_archive_enabled') && blank(AppSetting::s3SecretAccessKey()))
                            ->maxLength(255),
                        TextInput::make('s3_endpoint')
                            ->label('Endpoint')
                            ->placeholder('Opzionale')
                            ->maxLength(255),
                        Toggle::make('s3_use_path_style_endpoint')
                            ->label('Path style endpoint'),
                    ]),
                Section::make('Invio buste paga — Amazon SES SMTP')
                    ->description('Le credenziali SMTP di SES sono diverse dalle access key AWS. Il mittente deve essere verificato in Amazon SES.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('Host SMTP')
                            ->placeholder('email-smtp.eu-south-1.amazonaws.com')
                            ->maxLength(255),
                        TextInput::make('smtp_port')
                            ->label('Porta')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587),
                        Select::make('smtp_scheme')
                            ->label('Crittografia')
                            ->options(['smtp' => 'STARTTLS (porta 587)', 'smtps' => 'TLS implicito (porta 465)'])
                            ->default('smtp'),
                        TextInput::make('smtp_username')
                            ->label('Username SMTP')
                            ->maxLength(255),
                        TextInput::make('smtp_password')
                            ->label('Password SMTP')
                            ->password()
                            ->revealable()
                            ->helperText('Lasciare vuoto per mantenere la password già salvata.')
                            ->maxLength(255),
                        TextInput::make('smtp_from_address')
                            ->label('Email mittente')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('smtp_from_name')
                            ->label('Nome mittente')
                            ->maxLength(255),
                    ]),
                Section::make('OCR locale')
                    ->description(fn (): string => $this->ocrDescription())
                    ->schema([]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saveSettings();

        Notification::make()
            ->title('Impostazioni salvate')
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
                            Action::make('testS3')
                                ->label('Test connessione S3')
                                ->action('testS3'),
                            Action::make('syncS3')
                                ->label('Sincronizza archivio S3')
                                ->requiresConfirmation()
                                ->action('syncS3'),
                            Action::make('testSmtp')
                                ->label('Invia email SMTP di prova')
                                ->action('testSmtp'),
                            Action::make('save')
                                ->label('Salva impostazioni')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<int, mixed>  $mansioni
     * @return array<int, string>
     */
    private function sanitizeMansioni(array $mansioni): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $mansione): string => trim((string) $mansione),
            $mansioni,
        ))));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function syncS3(S3ArchiveService $archiveService): void
    {
        $this->saveSettings();

        $result = $archiveService->syncExistingArchive();

        if (! $result['enabled']) {
            Notification::make()
                ->title('Archivio S3 non configurato')
                ->body('Attiva S3 e verifica bucket, regione, access key e secret key.')
                ->danger()
                ->send();

            return;
        }

        $notification = Notification::make()
            ->title('Sincronizzazione S3 completata')
            ->body(sprintf(
                'Controllati: %d. Gia presenti: %d. Mancanti caricati: %d. File locali mancanti: %d. Errori: %d.',
                $result['checked'],
                $result['already_present'],
                $result['uploaded'],
                $result['local_missing'],
                $result['failed'],
            ));

        if ($result['failed'] > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    public function testS3(S3ArchiveService $archiveService): void
    {
        $this->saveSettings();

        $result = $archiveService->testConnection();

        if ($result['ok']) {
            Notification::make()
                ->title('Connessione S3 riuscita')
                ->body("File di diagnostica creato: {$result['path']}")
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['enabled'] ? 'Connessione S3 non riuscita' : 'Archivio S3 non configurato')
            ->body($result['message'])
            ->danger()
            ->send();
    }

    public function testSmtp(PayrollMailService $mailService): void
    {
        $this->saveSettings();

        try {
            $mailService->sendTest((string) auth()->user()->email);
            Notification::make()
                ->title('Email di prova inviata')
                ->body('Destinatario: '.auth()->user()->email)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Invio SMTP non riuscito')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function ocrDescription(): string
    {
        $diagnostics = app(LocalPayrollOcrService::class)->diagnostics();
        $tools = $diagnostics['ready'] ? 'Tesseract e Poppler disponibili.' : 'Tesseract o Poppler mancanti.';
        $language = in_array('ita', $diagnostics['languages'], true)
            ? 'Lingua italiana installata.'
            : 'Lingua italiana non installata: viene usato il modello inglese.';

        return "{$tools} {$language}";
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('amministratore') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private function saveSettings(): void
    {
        $data = $this->form->getState();

        AppSetting::setValue(AppSetting::PERMESSO_SOGGIORNO_ALERT_DAYS, (int) $data['permesso_soggiorno_alert_days']);
        AppSetting::setValue(AppSetting::VISITA_MEDICA_ALERT_DAYS, (int) $data['visita_medica_alert_days']);
        AppSetting::setValue(AppSetting::MANSIONI, $this->sanitizeMansioni($data['mansioni'] ?? []));
        AppSetting::setValue(AppSetting::S3_ARCHIVE_ENABLED, (bool) ($data['s3_archive_enabled'] ?? false));
        AppSetting::setValue(AppSetting::S3_ACCESS_KEY_ID, $this->nullableString($data['s3_access_key_id'] ?? null));
        AppSetting::setS3SecretAccessKey($this->nullableString($data['s3_secret_access_key'] ?? null));
        AppSetting::setValue(AppSetting::S3_DEFAULT_REGION, $this->nullableString($data['s3_default_region'] ?? null));
        AppSetting::setValue(AppSetting::S3_BUCKET, $this->nullableString($data['s3_bucket'] ?? null));
        AppSetting::setValue(AppSetting::S3_ENDPOINT, $this->nullableString($data['s3_endpoint'] ?? null));
        AppSetting::setValue(AppSetting::S3_USE_PATH_STYLE_ENDPOINT, (bool) ($data['s3_use_path_style_endpoint'] ?? false));
        AppSetting::setValue(AppSetting::SMTP_HOST, $this->nullableString($data['smtp_host'] ?? null));
        AppSetting::setValue(AppSetting::SMTP_PORT, (int) ($data['smtp_port'] ?? 587));
        AppSetting::setValue(AppSetting::SMTP_SCHEME, $this->nullableString($data['smtp_scheme'] ?? null));
        AppSetting::setValue(AppSetting::SMTP_USERNAME, $this->nullableString($data['smtp_username'] ?? null));
        AppSetting::setSmtpPassword($this->nullableString($data['smtp_password'] ?? null));
        AppSetting::setValue(AppSetting::SMTP_FROM_ADDRESS, $this->nullableString($data['smtp_from_address'] ?? null));
        AppSetting::setValue(AppSetting::SMTP_FROM_NAME, $this->nullableString($data['smtp_from_name'] ?? null));
    }
}
