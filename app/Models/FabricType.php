<?php

namespace App\Models;

use App\Support\FabricProfile;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع پارچه در بانک پایه (سراسری). */
#[Fillable([
    'code', 'name_fa', 'name_en', 'category', 'family', 'fiber_composition',
    'profile', 'care', 'sewing', 'description', 'sort', 'is_active',
])]
class FabricType extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'natural' => 'الیاف طبیعی',
        'synthetic' => 'الیاف مصنوعی',
        'blend' => 'ترکیبی',
    ];

    public const FAMILIES = [
        'woven' => 'تاری-پودی',
        'knit' => 'کشباف (کشی)',
        'lace' => 'توری و دانتل',
        'non_woven' => 'بی‌بافت',
    ];

    protected function casts(): array
    {
        return [
            'fiber_composition' => 'array',
            'profile' => 'array',
            'care' => 'array',
            'sewing' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function fabrics(): HasMany
    {
        return $this->hasMany(Fabric::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function fabricProfile(): FabricProfile
    {
        return FabricProfile::make($this->profile ?? []);
    }

    public function categoryLabel(): string
    {
        return static::CATEGORIES[$this->category] ?? $this->category;
    }

    public function familyLabel(): ?string
    {
        return $this->family ? (static::FAMILIES[$this->family] ?? $this->family) : null;
    }

    /** ترکیب الیاف به شکل «پنبه ۷۰٪ • پلی‌استر ۳۰٪». */
    public function compositionLabel(): string
    {
        return collect($this->fiber_composition ?? [])
            ->map(fn ($row) => ($row['name'] ?? $row['fiber'] ?? '').' '.Jalali::digits((string) ($row['percent'] ?? 0)).'٪')
            ->implode(' • ');
    }
}
