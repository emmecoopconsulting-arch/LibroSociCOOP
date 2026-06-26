<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'data_servizio',
    'titolo',
    'stato',
    'note',
    'pdf_path',
    'archiviato_il',
])]
class WorkOrder extends Model
{
    public const STATI = [
        'bozza' => 'Bozza',
        'archiviato' => 'Archiviato',
    ];

    protected function casts(): array
    {
        return [
            'data_servizio' => 'date',
            'archiviato_il' => 'datetime',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(WorkOrderSite::class)->orderBy('orario_inizio');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(WorkAbsence::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(WorkReport::class);
    }
}
