<?php

namespace App\Models;

use App\Models\Scopes\WorkshopScope;
use App\Support\Format;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * آگهی فروش یک الگو در بازارچه.
 *
 * برخلاف بیشتر مدل‌های برنامه، این مدل عمداً به کارگاه فعال محدود نمی‌شود (تِرِیت
 * BelongsToWorkshop را ندارد): ویترین بازارچه ذاتاً میان‌کارگاهی است و آگهی هر کارگاه
 * باید برای بقیه دیده شود. در عوض هر جا که «فقط مالِ خودم» لازم است، صریح با
 * seller_workshop_id محدود می‌شود.
 */
#[Fillable([
    'seller_workshop_id', 'pattern_id', 'garment_type_id', 'title', 'description',
    'price', 'currency', 'is_active', 'preview', 'tags',
])]
class PatternListing extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
            'preview' => 'array',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // نگهبان «هر الگو حداکثر یک آگهی فعال» در سطح دیتابیس
        static::saving(function (self $listing) {
            $listing->active_pattern_id = $listing->is_active ? $listing->pattern_id : null;
        });

        // حذف آگهی، تاریخچه خریدها را از بین نمی‌برد؛ فقط از ویترین برداشته می‌شود
        static::deleting(function (self $listing) {
            if (! $listing->isForceDeleting()) {
                $listing->forceFill(['is_active' => false, 'active_pattern_id' => null])->saveQuietly();
            }
        });
    }

    public function sellerWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'seller_workshop_id');
    }

    /** الگوی فروشنده؛ محدودیت کارگاه برداشته می‌شود تا خریدار هم بتواند آگهی را ببیند. */
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class)->withoutGlobalScope(WorkshopScope::class);
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PatternPurchase::class);
    }

    /** آگهی‌های روی ویترین. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** آگهی‌های یک کارگاه فروشنده. */
    public function scopeOfSeller(Builder $query, ?int $workshopId): Builder
    {
        return $query->where('seller_workshop_id', $workshopId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%"));
    }

    public function scopePriceBetween(Builder $query, float|int|null $min, float|int|null $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max));
    }

    public function isOwnedBy(?int $workshopId): bool
    {
        return $workshopId !== null && $this->seller_workshop_id === $workshopId;
    }

    /** آگهی غیرفعال یا حذف‌شده روی ویترین دیده نمی‌شود. */
    public function isVisible(): bool
    {
        return $this->is_active && $this->deleted_at === null;
    }

    public function priceLabel(): string
    {
        return $this->price > 0
            ? Format::money($this->price, $this->currency ?: 'تومان')
            : 'رایگان';
    }

    /** کلید دلخواه از داده پیش‌نمایش (چکیده‌ای که اصل الگو را لو نمی‌دهد). */
    public function previewValue(string $key, mixed $default = null): mixed
    {
        return ($this->preview ?? [])[$key] ?? $default;
    }
}
