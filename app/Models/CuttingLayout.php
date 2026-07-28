<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Support\Format;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** چیدمان قطعات روی پارچه و محاسبه مصرف. */
#[Fillable([
    'workshop_id', 'project_id', 'pattern_id', 'fabric_id', 'fabric_width_cm',
    'required_length_cm', 'waste_percent', 'efficiency', 'respect_nap',
    'match_stripes', 'folded', 'placements', 'warnings',
])]
class CuttingLayout extends Model
{
    use BelongsToWorkshop, HasFactory;

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'warnings' => 'array',
            'fabric_width_cm' => 'float',
            'required_length_cm' => 'float',
            'waste_percent' => 'float',
            'efficiency' => 'float',
            'respect_nap' => 'boolean',
            'match_stripes' => 'boolean',
            'folded' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    /** طول لازم به متر. */
    public function requiredMeters(): float
    {
        return round($this->required_length_cm / 100, 2);
    }

    /** هزینه پارچه بر پایه قیمت هر متر. */
    public function estimatedCost(): ?float
    {
        $price = $this->fabric?->price_per_meter;

        return $price ? round($this->requiredMeters() * $price) : null;
    }

    /** عرض قابل استفاده؛ پارچه تاشده تنها نیمی از عرض را در اختیار می‌گذارد. */
    public function usableWidthCm(): float
    {
        return round($this->folded ? $this->fabric_width_cm / 2 : $this->fabric_width_cm, 2);
    }

    /** مساحت پارچه مصرف‌شده (عرض مفید × طول) به سانتی‌متر مربع. */
    public function fabricAreaCm2(): float
    {
        return round($this->usableWidthCm() * $this->required_length_cm, 1);
    }

    /** مساحت خالص قطعه‌ها؛ از روی چیدمان ذخیره‌شده. */
    public function usedAreaCm2(): float
    {
        return round(collect($this->placements ?? [])->sum(fn ($p) => (float) ($p['area_cm2'] ?? 0)), 1);
    }

    public function placementCount(): int
    {
        return count($this->placements ?? []);
    }

    /** گزینه‌های چیدمان، آماده برای اجرای دوباره سرویس. */
    public function options(): array
    {
        return [
            'fabric_width_cm' => (float) $this->fabric_width_cm,
            'folded' => (bool) $this->folded,
            'respect_nap' => (bool) $this->respect_nap,
            'match_stripes' => (bool) $this->match_stripes,
        ];
    }

    /** خلاصه یک‌خطی برای پیام و سرصفحه: «۲.۳۵ متر پارچه لازم است؛ دورریز ۸.۷٪». */
    public function summaryText(): string
    {
        return sprintf(
            '%s متر پارچه لازم است؛ دورریز %s',
            Jalali::digits(number_format($this->requiredMeters(), 2)),
            Format::percent($this->waste_percent, 1),
        );
    }
}
