<?php

namespace App\Models;

use App\Services\WorkOrderScheduleValidator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_id',
    'socio_id',
    'tipo',
    'note',
])]
class WorkAbsence extends Model
{
    public const TIPI = [
        'malattia' => 'Malattia',
        'ferie' => 'Ferie',
        'permesso' => 'Permesso',
        'altra' => 'Altra assenza',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkAbsence $absence): void {
            app(WorkOrderScheduleValidator::class)->validateAbsence($absence);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
