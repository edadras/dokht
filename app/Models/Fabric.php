<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Models\Concerns\RecordsActivity;
use App\Support\FabricProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** پارچه موجود در کارگاه. */
#[Fillable([
    'workshop_id', 'fabric_type_id', 'name', 'color', 'color_hex', 'surface_pattern',
    'pattern_repeat_cm', 'width_cm', 'gsm', 'price_per_meter', 'stock_meters',
    'profile_overrides', 'texture_path', 'normal_map_path', 'roughness_map_path',
    'notes', 'is_active',
])]
class Fabric extends Model
{
    use BelongsToWorkshop, HasFactory, RecordsActivity, SoftDeletes;

    public const SURFACE_PATTERNS = [
        'plain' => 'ساده',
        'stripe' => 'راه‌راه',
        'plaid' => 'چهارخانه',
        'print' => 'چاپی',
        'textured' => 'بافت‌دار',
    ];

    protected function casts(): array
    {
        return [
            'profile_overrides' => 'array',
            'width_cm' => 'float',
            'pattern_repeat_cm' => 'float',
            'price_per_meter' => 'float',
            'stock_meters' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function fabricType(): BelongsTo
    {
        return $this->belongsTo(FabricType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('color', 'like', "%{$term}%"));
    }

    /** شناسنامه نهایی: مشخصات نوع پارچه + بازنویسی‌های این پارچه. */
    public function profile(): FabricProfile
    {
        $overrides = $this->profile_overrides ?? [];

        if ($this->gsm) {
            $overrides['weight_gsm'] = $this->gsm;
        }

        return FabricProfile::merge($this->fabricType?->profile ?? [], $overrides);
    }

    public function typeName(): string
    {
        return $this->fabricType?->name_fa ?? 'نامشخص';
    }

    public function surfacePatternLabel(): string
    {
        return static::SURFACE_PATTERNS[$this->surface_pattern] ?? $this->surface_pattern;
    }

    /** پارچه‌های راه‌راه و چهارخانه نیاز به تطبیق طرح در درزها دارند. */
    public function needsPatternMatching(): bool
    {
        return in_array($this->surface_pattern, ['stripe', 'plaid'], true);
    }

    /** پارچه خواب‌دار یا طرح جهت‌دار باید یک‌جهت بریده شود. */
    public function isDirectional(): bool
    {
        $profile = $this->profile();

        return (bool) $profile->get('has_nap')
            || (bool) $profile->get('directional')
            || $this->surface_pattern === 'print';
    }

    public function effectiveWidth(): float
    {
        return (float) ($this->width_cm ?: 140);
    }

    public function displayName(): string
    {
        return trim($this->name.($this->color ? ' — '.$this->color : ''));
    }
}
