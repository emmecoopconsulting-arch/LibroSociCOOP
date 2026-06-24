<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assemblea_id',
    'socio_id',
    'stato',
    'presente_at',
    'note',
])]
class AssembleaPresenza extends Model
{
    public const STATI = [
        'presente' => 'Presente',
        'assente' => 'Assente',
        'delega' => 'Delega',
    ];

    protected $table = 'assemblea_presenze';

    protected function casts(): array
    {
        return [
            'presente_at' => 'datetime',
        ];
    }

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class);
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
