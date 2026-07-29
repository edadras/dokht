<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workshop_id', 'garment_type_id', 'pattern_template_id', 'measurement_set_id', 'name',
    'base_size', 'measurements', 'ease', 'seam_allowances', 'params', 'sewing_relations',
    'version', 'notes', 'is_published',
])]
class Pattern extends Model
{
    use BelongsToWorkshop, HasFactory, RecordsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'ease' => 'array',
            'seam_allowances' => 'array',
            'params' => 'array',
            'sewing_relations' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PatternTemplate::class, 'pattern_template_id');
    }

    public function measurementSet(): BelongsTo
    {
        return $this->belongsTo(MeasurementSet::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PatternPiece::class)->orderBy('sort')->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PatternVersion::class)->orderByDesc('version');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** آگهی‌های بازارچه برای این الگو (حداکثر یکی از آن‌ها فعال است). */
    public function listings(): HasMany
    {
        return $this->hasMany(PatternListing::class);
    }

    /** آگهی فعال این الگو، اگر روی ویترین بازارچه باشد. */
    public function activeListing(): HasOne
    {
        return $this->hasOne(PatternListing::class)->where('is_active', true);
    }

    /** آیا این الگو همین حالا در بازارچه برای فروش گذاشته شده است؟ */
    public function isListed(): bool
    {
        return $this->listings()->where('is_active', true)->exists();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where('name', 'like', "%{$term}%");
    }

    public function seamAllowance(string $edge = 'default'): float
    {
        $allowances = $this->seam_allowances ?? [];

        return (float) ($allowances[$edge] ?? $allowances['default'] ?? 1.0);
    }

    /** مجموع مساحت قطعات به سانتی‌متر مربع (برای تخمین مصرف پارچه). */
    public function totalArea(): float
    {
        return round($this->pieces->sum(fn (PatternPiece $piece) => $piece->area() * $piece->cut_quantity), 1);
    }

    public function pieceCount(): int
    {
        return (int) $this->pieces->sum('cut_quantity');
    }
}
