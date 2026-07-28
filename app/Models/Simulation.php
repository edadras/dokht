<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نتیجه پوشاندن لباس روی مانکن و تحلیل تناسب. */
#[Fillable([
    'workshop_id', 'project_id', 'pattern_id', 'fabric_id', 'measurement_set_id',
    'pose', 'params', 'zones', 'warnings', 'suggestions', 'fit_score',
])]
class Simulation extends Model
{
    use BelongsToWorkshop, HasFactory;

    public const POSES = [
        'stand' => 'ایستاده',
        'walk' => 'راه رفتن',
        'arm_raise' => 'بالا بردن دست',
        'bend' => 'خم شدن',
        'sit' => 'نشستن',
        'turn' => 'چرخش',
    ];

    /** رنگ‌بندی نقشه فشار. */
    public const LEVELS = [
        'tight' => ['label' => 'تنگ', 'color' => '#dc2626'],
        'snug' => ['label' => 'چسبان', 'color' => '#f59e0b'],
        'good' => ['label' => 'مناسب', 'color' => '#16a34a'],
        'loose' => ['label' => 'گشاد', 'color' => '#2563eb'],
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'zones' => 'array',
            'warnings' => 'array',
            'suggestions' => 'array',
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

    public function measurementSet(): BelongsTo
    {
        return $this->belongsTo(MeasurementSet::class);
    }

    public function poseLabel(): string
    {
        return static::POSES[$this->pose] ?? $this->pose;
    }

    public static function levelColor(string $level): string
    {
        return static::LEVELS[$level]['color'] ?? '#6b7280';
    }

    public static function levelLabel(string $level): string
    {
        return static::LEVELS[$level]['label'] ?? $level;
    }

    public function hasProblems(): bool
    {
        return ! empty($this->warnings);
    }
}
