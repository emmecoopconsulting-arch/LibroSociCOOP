<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome',
    'luogo',
    'note',
])]
class WorkSite extends Model
{
    public function getDisplayNameAttribute(): string
    {
        return filled($this->luogo) ? "{$this->nome} - {$this->luogo}" : $this->nome;
    }

    public static function labels(): array
    {
        return static::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (WorkSite $site): string => $site->display_name)
            ->all();
    }

    public static function idForLabel(?string $label): ?int
    {
        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        return static::query()
            ->get()
            ->first(fn (WorkSite $site): bool => $site->display_name === $label || $site->nome === $label)
            ?->id;
    }

    public static function findOrCreateForLabel(?string $label): ?self
    {
        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        $existing = static::query()
            ->get()
            ->first(fn (WorkSite $site): bool => $site->display_name === $label || $site->nome === $label);

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'nome' => $label,
            'luogo' => '',
        ]);
    }

    public function orderSites(): HasMany
    {
        return $this->hasMany(WorkOrderSite::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(WorkReport::class);
    }
}
