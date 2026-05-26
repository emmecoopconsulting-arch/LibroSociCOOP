<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'logo_path',
    'text',
])]
class DocumentHeaderSetting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
