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
    public function orderSites(): HasMany
    {
        return $this->hasMany(WorkOrderSite::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(WorkReport::class);
    }
}
