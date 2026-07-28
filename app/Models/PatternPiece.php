<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک قطعه الگو.
 *
 * outline آرایه‌ای از نقاط است: [{x, y, curve?: bool, cx?, cy?}] که به ترتیب به هم
 * وصل می‌شوند و در انتها به نقطه اول بسته می‌شوند. مبنای واحد سانتی‌متر است.
 */
#[Fillable([
    'pattern_id', 'code', 'name', 'layer', 'cut_quantity', 'on_fold', 'mirror', 'outline',
    'grainline', 'darts', 'notches', 'drills', 'pleats', 'markers', 'edge_allowances', 'meta', 'sort',
])]
class PatternPiece extends Model
{
    use HasFactory;

    public const LAYERS = [
        'outer' => 'پارچه رو',
        'lining' => 'آستر',
        'interfacing' => 'لایی',
        'padding' => 'لایه حجم‌دهنده',
        'underlining' => 'زیرلایه',
    ];

    protected function casts(): array
    {
        return [
            'outline' => 'array',
            'grainline' => 'array',
            'darts' => 'array',
            'notches' => 'array',
            'drills' => 'array',
            'pleats' => 'array',
            'markers' => 'array',
            'edge_allowances' => 'array',
            'meta' => 'array',
            'on_fold' => 'boolean',
            'mirror' => 'boolean',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function layerLabel(): string
    {
        return static::LAYERS[$this->layer] ?? $this->layer;
    }

    /** @return array<int, array{x: float, y: float}> */
    public function points(): array
    {
        return collect($this->outline ?? [])
            ->map(fn ($p) => ['x' => (float) ($p['x'] ?? 0), 'y' => (float) ($p['y'] ?? 0)])
            ->all();
    }

    /** کادر دربرگیرنده قطعه: [minX, minY, maxX, maxY]. */
    public function bounds(): array
    {
        $points = $this->points();

        if ($points === []) {
            return [0, 0, 0, 0];
        }

        $xs = array_column($points, 'x');
        $ys = array_column($points, 'y');

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    public function width(): float
    {
        [$minX, , $maxX] = $this->bounds();

        return round($maxX - $minX, 2);
    }

    public function height(): float
    {
        [, $minY, , $maxY] = $this->bounds();

        return round($maxY - $minY, 2);
    }

    /** مساحت چندضلعی به روش شوی‌لِیس. */
    public function area(): float
    {
        $points = $this->points();
        $count = count($points);

        if ($count < 3) {
            return 0.0;
        }

        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $sum += ($a['x'] * $b['y']) - ($b['x'] * $a['y']);
        }

        return round(abs($sum) / 2, 2);
    }

    /** محیط قطعه به سانتی‌متر. */
    public function perimeter(): float
    {
        $points = $this->points();
        $count = count($points);
        $total = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $total += sqrt((($b['x'] - $a['x']) ** 2) + (($b['y'] - $a['y']) ** 2));
        }

        return round($total, 2);
    }

    /** جای دوخت یک لبه؛ اگر مقدار اختصاصی نداشته باشد از پیش‌فرض الگو استفاده می‌شود. */
    public function allowanceFor(int $edgeIndex, float $default = 1.0): float
    {
        $allowances = $this->edge_allowances ?? [];

        return (float) ($allowances[$edgeIndex] ?? $allowances['default'] ?? $default);
    }
}
