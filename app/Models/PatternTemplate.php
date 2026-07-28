<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** الگوی پایه پارامتریک در کتابخانه. */
#[Fillable([
    'workshop_id', 'garment_type_id', 'code', 'name_fa', 'generator', 'default_params',
    'params_schema', 'description', 'preview_svg', 'is_public', 'sort',
])]
class PatternTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'default_params' => 'array',
            'params_schema' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /** الگوهای عمومی و الگوهای همین کارگاه. */
    public function scopeAvailableTo(Builder $query, ?int $workshopId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('workshop_id')
            ->orWhere('workshop_id', $workshopId));
    }

    public function params(array $overrides = []): array
    {
        return array_merge($this->default_params ?? [], $overrides);
    }
}
