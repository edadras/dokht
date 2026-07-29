<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Support\Measurements;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * پروژه دوخت یک لباس.
 *
 * پروژه همه چیز را به هم وصل می‌کند: مشتری، اندازه، پارچه، الگو، شبیه‌سازی و برش.
 * مراحل کار به صورت گام‌به‌گام پیش می‌رود و کاربر لازم نیست همه را یک‌جا پر کند.
 */
#[Fillable([
    'workshop_id', 'customer_id', 'garment_type_id', 'measurement_set_id', 'fabric_id',
    'lining_fabric_id', 'pattern_id', 'name', 'size', 'status', 'step', 'ease',
    'settings', 'scorecard', 'notes',
])]
class Project extends Model
{
    use BelongsToWorkshop, HasFactory, SoftDeletes;

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'in_progress' => 'در حال کار',
        'done' => 'تمام‌شده',
        'archived' => 'بایگانی',
    ];

    /** مراحل کار پروژه؛ کلید هر مرحله در مسیرها استفاده می‌شود. */
    public const STEPS = [
        1 => ['key' => 'design', 'title' => 'انتخاب مدل', 'icon' => 'shirt'],
        2 => ['key' => 'body', 'title' => 'اندازه بدن', 'icon' => 'ruler'],
        3 => ['key' => 'fabric', 'title' => 'انتخاب پارچه', 'icon' => 'layers'],
        4 => ['key' => 'pattern', 'title' => 'الگو', 'icon' => 'scissors'],
        5 => ['key' => 'seam', 'title' => 'جای دوخت', 'icon' => 'stitch'],
        6 => ['key' => 'layout', 'title' => 'چیدمان دوبعدی', 'icon' => 'grid'],
        7 => ['key' => 'simulation', 'title' => 'نمای سه‌بعدی', 'icon' => 'cube'],
        8 => ['key' => 'fit', 'title' => 'تست تناسب', 'icon' => 'check'],
        9 => ['key' => 'cutting', 'title' => 'برش', 'icon' => 'cut'],
        10 => ['key' => 'techpack', 'title' => 'کارت فنی', 'icon' => 'file'],
        11 => ['key' => 'export', 'title' => 'خروجی', 'icon' => 'download'],
    ];

    protected function casts(): array
    {
        return [
            'ease' => 'array',
            'settings' => 'array',
            'scorecard' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function measurementSet(): BelongsTo
    {
        return $this->belongsTo(MeasurementSet::class);
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function liningFabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class, 'lining_fabric_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class)->latest('id');
    }

    public function latestSimulation(): HasOne
    {
        return $this->hasOne(Simulation::class)->latestOfMany();
    }

    /** نوبت‌های پرو روی تن مشتری. */
    public function fittings(): HasMany
    {
        return $this->hasMany(Fitting::class);
    }

    public function cuttingLayouts(): HasMany
    {
        return $this->hasMany(CuttingLayout::class)->latest('id');
    }

    public function latestCuttingLayout(): HasOne
    {
        return $this->hasOne(CuttingLayout::class)->latestOfMany();
    }

    public function techPacks(): HasMany
    {
        return $this->hasMany(TechPack::class)->orderByDesc('version');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'in_progress']);
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status] ?? $this->status;
    }

    public function stepTitle(): string
    {
        return static::STEPS[$this->step]['title'] ?? '—';
    }

    /** کدام مراحل تکمیل شده‌اند. */
    public function completedSteps(): array
    {
        return [
            1 => $this->garment_type_id !== null,
            2 => $this->measurement_set_id !== null,
            3 => $this->fabric_id !== null,
            4 => $this->pattern_id !== null,
            5 => $this->pattern_id !== null && ! empty($this->pattern?->seam_allowances),
            6 => $this->pattern_id !== null,
            7 => $this->simulations()->exists(),
            8 => $this->simulations()->whereNotNull('fit_score')->exists(),
            9 => $this->cuttingLayouts()->exists(),
            10 => $this->techPacks()->exists(),
            11 => $this->techPacks()->exists(),
        ];
    }

    public function progressPercent(): int
    {
        $steps = $this->completedSteps();
        $done = count(array_filter($steps));

        return (int) round($done / max(1, count($steps)) * 100);
    }

    /** آزادی مؤثر: تنظیم پروژه یا پیش‌فرض نوع لباس. */
    public function effectiveEase(): array
    {
        return array_merge($this->garmentType?->ease() ?? [], $this->ease ?? []);
    }

    /** گام بعدی که کاربر باید انجام دهد. */
    public function nextStep(): int
    {
        foreach ($this->completedSteps() as $step => $done) {
            if (! $done) {
                return $step;
            }
        }

        return 11;
    }

    /** شماره مرحله از روی کلید آن؛ برای مسیرهای گام‌به‌گام. */
    public static function stepNumber(string $key): ?int
    {
        foreach (static::STEPS as $number => $step) {
            if ($step['key'] === $key) {
                return $number;
            }
        }

        return null;
    }

    public static function stepKey(int $number): ?string
    {
        return static::STEPS[$number]['key'] ?? null;
    }

    public function nextStepKey(): string
    {
        return static::stepKey($this->nextStep()) ?? 'export';
    }

    /**
     * اندازه‌های بدن این پروژه.
     *
     * ترتیب: دفترچه اندازه مشتری، سایز استاندارد انتخاب‌شده، و در نهایت سایز ۴۰
     * تا هیچ صفحه‌ای بدون عدد نماند.
     */
    public function resolvedMeasurements(): array
    {
        if ($this->measurementSet) {
            return $this->measurementSet->completed();
        }

        return Measurements::fromSize($this->size ?: '40');
    }

    /** یک بخش از کارنامه کیفیت. */
    public function scoreOf(string $key): ?int
    {
        $value = $this->scorecard[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
