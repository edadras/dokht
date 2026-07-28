<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع لباس و اجزای تشکیل‌دهنده آن. */
#[Fillable([
    'code', 'name_fa', 'name_en', 'category', 'parts', 'default_ease',
    'required_measurements', 'fabric_preferences', 'icon', 'sort', 'is_active',
])]
class GarmentType extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'top' => 'بالاتنه',
        'bottom' => 'پایین‌تنه',
        'one_piece' => 'یک‌تکه',
        'outer' => 'رویه و کت',
        'formal' => 'مجلسی',
    ];

    /** نام فارسی اجزای لباس. */
    public const PART_LABELS = [
        'front_bodice' => 'بالاتنه جلو',
        'back_bodice' => 'بالاتنه پشت',
        'sleeve' => 'آستین',
        'collar' => 'یقه',
        'cuff' => 'مچ آستین',
        'skirt_front' => 'دامن جلو',
        'skirt_back' => 'دامن پشت',
        'front_panel' => 'پنل جلو',
        'back_panel' => 'پنل پشت',
        'waistband' => 'کمربند',
        'pocket' => 'جیب',
        'facing' => 'پاتلت و سجاف',
        'lining' => 'آستر',
        'interfacing' => 'لایی',
        'front_leg' => 'پای جلو',
        'back_leg' => 'پای پشت',
        'yoke' => 'سرشانه (یوک)',
        'placket' => 'جای دکمه',
        'lapel' => 'برگردان یقه',
        'hood' => 'کلاه',
        'belt' => 'کمربند تزئینی',
    ];

    protected function casts(): array
    {
        return [
            'parts' => 'array',
            'default_ease' => 'array',
            'required_measurements' => 'array',
            'fabric_preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function patternTemplates(): HasMany
    {
        return $this->hasMany(PatternTemplate::class);
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(Pattern::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return static::CATEGORIES[$this->category] ?? $this->category;
    }

    /** @return array<int, string> */
    public function partLabels(): array
    {
        return collect($this->parts ?? [])
            ->map(fn ($part) => static::PART_LABELS[$part] ?? $part)
            ->all();
    }

    public static function partLabel(string $part): string
    {
        return static::PART_LABELS[$part] ?? $part;
    }

    /** آزادی پیش‌فرض این نوع لباس. */
    public function ease(): array
    {
        return array_merge(['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4], $this->default_ease ?? []);
    }
}
