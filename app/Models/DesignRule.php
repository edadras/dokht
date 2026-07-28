<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قاعده طراحی و برش.
 *
 * قواعد به صورت داده ذخیره می‌شوند تا دانش خیاطی بدون تغییر کد قابل توسعه باشد.
 */
#[Fillable([
    'workshop_id', 'code', 'name', 'scope', 'conditions', 'actions',
    'severity', 'priority', 'is_active',
])]
class DesignRule extends Model
{
    use HasFactory;

    public const SCOPES = [
        'fabric' => 'پارچه',
        'pattern' => 'الگو',
        'cutting' => 'برش',
        'fit' => 'تناسب لباس',
        'production' => 'تولید',
    ];

    public const SEVERITIES = [
        'info' => 'اطلاع',
        'suggestion' => 'پیشنهاد',
        'warning' => 'هشدار',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** قواعد عمومی به‌همراه قواعد اختصاصی کارگاه. */
    public function scopeAvailableTo(Builder $query, ?int $workshopId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('workshop_id')
            ->orWhere('workshop_id', $workshopId));
    }

    public function scopeLabel(): string
    {
        return static::SCOPES[$this->scope] ?? $this->scope;
    }

    public function severityLabel(): string
    {
        return static::SEVERITIES[$this->severity] ?? $this->severity;
    }
}
