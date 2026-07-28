<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه مشترک تولیدکننده‌های الگو.
 *
 * همه محاسبات روی «یک‌چهارم» بدن انجام می‌شود: هر قطعه نیم‌قطعه است و اگر روی دو
 * لای پارچه بریده شود on_fold می‌گیرد. مبدأ هر قطعه گوشه بالا-چپ خودش است.
 */
abstract class BaseGenerator implements PatternGenerator
{
    /** مقادیر پیش‌فرض از دل توضیح پارامترها خوانده می‌شود تا در یک جا نگه‌داری شود. */
    public function defaultParams(): array
    {
        return collect($this->paramsSchema())->map(fn (array $field) => $field['default'])->all();
    }

    /** یک اندازه بدن با پیش‌فرض مطمئن. */
    protected function m(array $measurements, string $key, float $fallback = 0.0): float
    {
        $value = (float) ($measurements[$key] ?? 0);

        return $value > 0 ? $value : $fallback;
    }

    /** آزادی یک ناحیه؛ مقدار منفی برای پارچه کشی مجاز است. */
    protected function ease(array $ease, string $key, float $default = 0.0): float
    {
        return array_key_exists($key, $ease) && is_numeric($ease[$key]) ? (float) $ease[$key] : $default;
    }

    protected function param(array $params, string $key, mixed $default = null): mixed
    {
        $value = $params[$key] ?? $default;

        if (is_numeric($value) && is_numeric($default ?? 0)) {
            return (float) $value;
        }

        return $value;
    }

    protected function flag(array $params, string $key, bool $default = false): bool
    {
        $value = $params[$key] ?? $default;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * اندازه‌های کلیدی درفت بالاتنه.
     *
     * فرمول‌ها همان چیزی است که در خیاطی استفاده می‌شود:
     * پهنای نیم‌بالاتنه = (دور سینه + آزادی) ÷ ۴، گودی حلقه ≈ دور سینه ÷ ۸ + ۷،
     * عرض یقه ≈ دور گردن ÷ ۵ + ۰٫۵ و کاهش کمر = (سینه − کمر) ÷ ۴.
     *
     * @return array<string, float>
     */
    protected function bodiceMetrics(array $m, array $ease, array $params): array
    {
        $bust = $this->m($m, 'bust', 92);
        $waist = $this->m($m, 'waist', 74);
        $hip = $this->m($m, 'hip', 98);
        $neck = $this->m($m, 'neck', $bust * 0.4);
        $shoulderWidth = $this->m($m, 'shoulder_width', 39);

        $bustEase = $this->ease($ease, 'bust', 6);
        $waistEase = $this->ease($ease, 'waist', 4);
        $hipEase = $this->ease($ease, 'hip', 6);

        $shoulderHalf = $shoulderWidth / 2;
        $quarterBust = max($shoulderHalf + 1.5, ($bust + $bustEase) / 4);
        $quarterWaist = max(8.0, ($waist + $waistEase) / 4);
        $quarterHip = max(10.0, ($hip + $hipEase) / 4);

        $armholeDepth = ($bust / 8) + 7 + (float) $this->param($params, 'armhole_depth_extra', 0);
        $neckWidth = ($neck / 5) + 0.5 + (float) $this->param($params, 'neck_width_extra', 0);
        $frontNeckDepth = $neckWidth + 1 + (float) $this->param($params, 'front_neck_depth_extra', 0);
        $backNeckDepth = (float) $this->param($params, 'back_neck_depth', 2);

        $lengthExtra = (float) $this->param($params, 'bodice_length_extra', 0);
        $backWaistY = $this->m($m, 'back_length', 41) + $lengthExtra;
        $frontWaistY = $this->m($m, 'front_length', 43) + $lengthExtra;

        $intake = max(0.0, $quarterBust - $quarterWaist);
        $dartShare = min(0.9, max(0.0, (float) $this->param($params, 'waist_dart_share', 0.6)));

        return [
            'bust' => $bust + $bustEase,
            'waist' => $waist + $waistEase,
            'hip' => $hip + $hipEase,
            'quarter_bust' => round($quarterBust, 2),
            'quarter_waist' => round($quarterWaist, 2),
            'quarter_hip' => round($quarterHip, 2),
            'bust_y' => round($armholeDepth, 2),
            'neck_width' => round($neckWidth, 2),
            'front_neck_depth' => round($frontNeckDepth, 2),
            'back_neck_depth' => round($backNeckDepth, 2),
            'shoulder_half' => round($shoulderHalf, 2),
            'shoulder_drop' => (float) $this->param($params, 'shoulder_slope', 4.5),
            'front_waist_y' => round($frontWaistY, 2),
            'back_waist_y' => round($backWaistY, 2),
            'hip_drop' => round($this->m($m, 'waist_to_hip', 21), 2),
            'across_chest' => round($quarterBust - 3.5, 2),
            'across_back' => round($quarterBust - 2.6, 2),
            'waist_intake' => round($intake, 2),
            'dart_intake' => round($intake * $dartShare, 2),
            'side_intake' => round($intake * (1 - $dartShare), 2),
            'bust_apex_x' => round($bust * 0.105, 2),
            'bust_apex_y' => round(min($frontWaistY - 6, $this->m($m, 'shoulder_to_bust', 26)), 2),
            'bust_dart_intake' => round(max(1.0, ($bust - $this->m($m, 'under_bust', $bust - 14)) / 4), 2),
        ];
    }

    /**
     * ساخت یک قطعه با کلیدهای کامل و مرتب.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function piece(array $attributes): array
    {
        $piece = array_merge([
            'code' => 'piece',
            'name' => 'قطعه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [],
            'grainline' => null,
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'edge_allowances' => [],
            'meta' => [],
            'sort' => 0,
        ], $attributes);

        $piece['outline'] = Geometry::round($piece['outline']);

        return Geometry::normalizePiece($piece);
    }

    /** خط راستای پارچه: پیکانی موازی مرکز قطعه. */
    protected function grainline(float $x, float $fromY, float $toY): array
    {
        return [
            'from' => Geometry::point($x, $fromY),
            'to' => Geometry::point($x, $toY),
            'label' => 'راستای پارچه',
        ];
    }

    /** خط نشانه افقی (سینه، کمر، باسن). */
    protected function marker(string $key, string $label, float $fromX, float $y, float $toX, ?float $toY = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'from' => Geometry::point($fromX, $y),
            'to' => Geometry::point($toX, $toY ?? $y),
        ];
    }

    /**
     * ساسون: دو پا روی یک لبه و یک نوک.
     *
     * $axis مشخص می‌کند پاهای ساسون در راستای افقی باز می‌شوند (ساسون کمر) یا عمودی
     * (ساسون سینه روی درز پهلو).
     */
    protected function dart(
        string $type,
        string $label,
        ?int $edge,
        float $centerX,
        float $centerY,
        float $intake,
        float $apexX,
        float $apexY,
        string $axis = 'x',
        array $extra = [],
    ): array {
        $half = $intake / 2;

        $legs = $axis === 'y'
            ? [Geometry::point($centerX, $centerY - $half), Geometry::point($centerX, $centerY + $half)]
            : [Geometry::point($centerX - $half, $centerY), Geometry::point($centerX + $half, $centerY)];

        return array_merge([
            'type' => $type,
            'label' => $label,
            'edge' => $edge,
            'axis' => $axis,
            'intake' => round($intake, 2),
            'center' => Geometry::point($centerX, $centerY),
            'apex' => Geometry::point($apexX, $apexY),
            'legs' => $legs,
        ], $extra);
    }

    /** علامت جفت‌شدن دو لبه هنگام دوخت. */
    protected function notch(float $x, float $y, int $edge, string $label, ?string $pair = null): array
    {
        return array_filter([
            'x' => round($x, 2),
            'y' => round($y, 2),
            'edge' => $edge,
            'label' => $label,
            'pair' => $pair,
        ], fn ($value) => $value !== null);
    }

    /**
     * شماره‌گذاری و مرتب‌سازی نهایی قطعه‌ها.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        $sort = 0;

        return array_map(function (array $piece) use (&$sort) {
            $piece['sort'] = $sort;
            $sort += 10;

            return $piece;
        }, array_values($pieces));
    }
}
