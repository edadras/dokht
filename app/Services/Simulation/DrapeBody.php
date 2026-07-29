<?php

namespace App\Services\Simulation;

/**
 * همان مانکنی که مرورگر می‌سازد، ولی روی سرور.
 *
 * ترازها و شعاع‌ها مو‌به‌مو از buildModel() در resources/js/components/garment-viewer.js
 * برداشته شده‌اند و نامشان هم همان است، چون بسته «چیدن اولیه» فقط نام تراز را
 * می‌فرستد و مرورگر عدد را از جدول خودش برمی‌دارد. اگر این دو جدول از هم دور
 * بیفتند، قطعه‌ها روی بدن جابه‌جا می‌نشینند.
 *
 * @internal
 */
final class DrapeBody
{
    /** ارتفاع هر تراز بدن، ضریبی از قد. */
    public const LEVELS = [
        'ankle' => 0.045,
        'knee' => 0.28,
        'crotch' => 0.475,
        'hip' => 0.53,
        'highHip' => 0.575,
        'waist' => 0.625,
        'underBust' => 0.69,
        'bust' => 0.725,
        'armhole' => 0.775,
        'shoulder' => 0.82,
        'neck' => 0.855,
        'chin' => 0.885,
        'top' => 1.0,
    ];

    /** قد بدن به سانتی‌متر. */
    public readonly float $height;

    /** @var array<string, float> شعاع هر تراز به سانتی‌متر */
    public readonly array $radii;

    /** بلندی دست به سانتی‌متر (برای پیدا کردن تراز مچ). */
    public readonly float $armLength;

    /**
     * @param  array<string, float|int|string>  $body  اندازه‌های کامل‌شده بدن
     */
    public function __construct(array $body)
    {
        $get = fn (string $key, float $fallback) => ((float) ($body[$key] ?? 0)) > 0
            ? (float) $body[$key]
            : $fallback;

        $this->height = max(80.0, $get('height', 165.0));

        $bust = $get('bust', 92.0);
        $hip = $get('hip', 98.0);

        $this->radii = [
            'hip' => self::radius($hip),
            'highHip' => self::radius($get('high_hip', $hip - 8)),
            'waist' => self::radius($get('waist', 74.0)),
            'underBust' => self::radius($get('under_bust', $bust - 14)),
            'bust' => self::radius($bust),
            'neck' => self::radius($get('neck', 36.0)),
            'armhole' => self::radius($get('armhole', 40.0)),
            'bicep' => self::radius($get('bicep', 28.0)),
            'wrist' => self::radius($get('wrist', 16.0)),
            'thigh' => self::radius($get('thigh', 56.0)),
            'knee' => self::radius($get('knee', 37.0)),
            'ankle' => self::radius($get('ankle', 23.0)),
            'shoulder' => $get('shoulder_width', 39.0) / 2,
        ];

        $this->armLength = $get('arm_length', 58.0);
    }

    /** شعاع یک دور بدن. */
    public static function radius(float $girth): float
    {
        return max(1.0, $girth) / (2 * M_PI);
    }

    /** ارتفاع یک تراز، ضریبی از قد. */
    public function level(string $key): float
    {
        return self::LEVELS[$key] ?? 0.625;
    }

    /** ارتفاع مچ دست، ضریبی از قد (دست آویزان از تراز حلقه آستین). */
    public function wristLevel(): float
    {
        return max(0.06, self::LEVELS['armhole'] - ($this->armLength / $this->height));
    }

    /**
     * نزدیک‌ترین تراز بدن به یک ارتفاع، از میان نامزدهای داده‌شده.
     *
     * @param  array<int, string>  $candidates  نام شعاع‌ها (کلید جدول radii)
     */
    public function nearestRadius(float $atHeight, array $candidates, array $levels = []): string
    {
        $best = $candidates[0] ?? 'bust';
        $bestGap = INF;

        foreach ($candidates as $key) {
            $level = $levels[$key] ?? self::LEVELS[$key] ?? 0.625;
            $gap = abs($level - $atHeight);

            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = $key;
            }
        }

        return $best;
    }
}
