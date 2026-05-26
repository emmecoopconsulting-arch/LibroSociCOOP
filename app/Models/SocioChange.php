<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['socio_id', 'user_id', 'field', 'old_value', 'new_value'])]
class SocioChange extends Model
{
    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
