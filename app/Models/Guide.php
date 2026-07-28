<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** راهنمای آموزشی برش و دوخت. */
#[Fillable([
    'scope', 'fabric_type_id', 'garment_type_id', 'title', 'summary', 'body', 'tips', 'sort',
])]
class Guide extends Model
{
    use HasFactory;

    public const SCOPES = [
        'fabric_type' => 'راهنمای پارچه',
        'garment_type' => 'راهنمای مدل لباس',
        'general' => 'راهنمای عمومی',
    ];

    protected function casts(): array
    {
        return ['tips' => 'array'];
    }

    public function fabricType(): BelongsTo
    {
        return $this->belongsTo(FabricType::class);
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function scopeLabel(): string
    {
        return static::SCOPES[$this->scope] ?? $this->scope;
    }
}
