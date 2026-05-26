<?php

namespace App\Filament\Pages;

use App\Models\DocumentHeaderSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class IntestazioneDocumenti extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Intestazione documenti';

    protected static ?string $title = 'Intestazione documenti';

    protected static string|UnitEnum|null $navigationGroup = 'Libro soci';

    protected string $view = 'filament.pages.intestazione-documenti';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(DocumentHeaderSetting::current()->only(['logo_path', 'text']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Intestazione generale')
                    ->description('Logo e testo saranno inseriti in alto nei PDF generati.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('document-header')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->helperText('Formato consigliato: PNG o JPG, massimo 2 MB.'),
                        Textarea::make('text')
                            ->label('Testo intestazione')
                            ->rows(6)
                            ->maxLength(2000)
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $setting = DocumentHeaderSetting::current();

        $setting->update($this->sanitizeFormData($this->form->getState()));
        $this->form->fill($setting->fresh()->only(['logo_path', 'text']));

        Notification::make()
            ->title('Intestazione salvata')
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
                            Action::make('save')
                                ->label('Salva intestazione')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{logo_path: string|null, text: string|null}
     */
    private function sanitizeFormData(array $data): array
    {
        return [
            'logo_path' => filled($data['logo_path'] ?? null) ? (string) $data['logo_path'] : null,
            'text' => filled($data['text'] ?? null) ? mb_convert_encoding((string) $data['text'], 'UTF-8', 'UTF-8') : null,
        ];
    }
}
