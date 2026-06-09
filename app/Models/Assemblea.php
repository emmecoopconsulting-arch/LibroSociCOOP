<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'data_assemblea',
    'titolo',
    'note',
    'stato',
    'file_path',
    'generato_il',
])]
class Assemblea extends Model
{
    public const STATI = [
        'generata' => 'Generata',
    ];

    protected $table = 'assemblee';

    protected function casts(): array
    {
        return [
            'data_assemblea' => 'date',
            'generato_il' => 'datetime',
        ];
    }

    public function variations(): HasMany
    {
        return $this->hasMany(SocioVariation::class);
    }
}
