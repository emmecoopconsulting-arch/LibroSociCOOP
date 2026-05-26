<?php

namespace App\Filament\Pages;

use App\Services\ApplicationUpdateService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class Aggiornamenti extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Aggiornamenti';

    protected static ?string $title = 'Aggiornamenti';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected string $view = 'filament.pages.aggiornamenti';

    public ?array $status = null;

    public array $log = [];

    public function mount(ApplicationUpdateService $service): void
    {
        $this->check($service, notify: true);
    }

    public function check(?ApplicationUpdateService $service = null, bool $notify = false): void
    {
        try {
            $this->status = ($service ?? app(ApplicationUpdateService::class))->check();

            if ($notify && ($this->status['available'] ?? false)) {
                Notification::make()
                    ->title('Nuova versione disponibile')
                    ->body($this->status['message'])
                    ->warning()
                    ->send();
            }
        } catch (Throwable $exception) {
            $this->status = [
                'available' => false,
                'dirty' => false,
                'message' => $exception->getMessage(),
            ];

            Notification::make()
                ->title('Controllo aggiornamenti non riuscito')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function update(ApplicationUpdateService $service): void
    {
        try {
            $this->log = $service->update();
            $this->status = $service->check();

            Notification::make()
                ->title('Aggiornamento completato')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Aggiornamento non riuscito')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
